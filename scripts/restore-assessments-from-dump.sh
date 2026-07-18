#!/usr/bin/env bash
#
# One-shot restore: copy school identity + assessments + assessment_scores
# from a pre-drop dump into a post-drop production DB.
#
# Context: the 2026_05_12_000001_backfill_and_drop_legacy_assessment_tables
# migration was supposed to copy legacy tests/exams into the new assessments
# table before dropping the legacy stack. Its backfill step short-circuits
# if no `schools` row exists — but production never had one (it ran on the
# old school_parameters key/value config). So on production the legacy
# tables were dropped with the migration's backfill silently no-opping.
# The assessments/scores rows are now gone from prod, but still live in
# the older snapshot.
#
# Usage:
#   ./restore-assessments-from-dump.sh <source_db> <target_db> [--apply]
#
# Defaults to dry-run (prints counts, doesn't write). Pass --apply to commit.
#
# Pre-flight: this script ASSUMES the target DB already has its school + the
# two assessment_types rows (id 1=Test, 2=Exam) — the
# 2026_05_12_000003_ensure_default_school_and_assessment_types migration
# creates these. Re-run that migration first if you haven't.

set -euo pipefail

SOURCE="${1:-}"
TARGET="${2:-}"
APPLY="${3:-}"

if [[ -z "$SOURCE" || -z "$TARGET" ]]; then
    echo "Usage: $0 <source_db> <target_db> [--apply]"
    echo "  source_db: DB containing the data to copy (pre-drop snapshot)"
    echo "  target_db: DB to restore into (post-drop production)"
    echo "  --apply:   actually write (default is dry-run)"
    exit 1
fi

run_query() {
    local db="$1" sql="$2"
    mysql -N -B "$db" -e "$sql"
}

echo "== Source: $SOURCE =="
echo "  schools:           $(run_query "$SOURCE" 'SELECT COUNT(*) FROM schools')"
echo "  assessment_types:  $(run_query "$SOURCE" 'SELECT COUNT(*) FROM assessment_types')"
echo "  assessments:       $(run_query "$SOURCE" 'SELECT COUNT(*) FROM assessments')"
echo "  assessment_scores: $(run_query "$SOURCE" 'SELECT COUNT(*) FROM assessment_scores')"
echo "  assessment_topic:  $(run_query "$SOURCE" 'SELECT COUNT(*) FROM assessment_topic')"

echo ""
echo "== Target: $TARGET (before) =="
echo "  schools:           $(run_query "$TARGET" 'SELECT COUNT(*) FROM schools')"
echo "  assessment_types:  $(run_query "$TARGET" 'SELECT COUNT(*) FROM assessment_types')"
echo "  assessments:       $(run_query "$TARGET" 'SELECT COUNT(*) FROM assessments')"
echo "  assessment_scores: $(run_query "$TARGET" 'SELECT COUNT(*) FROM assessment_scores')"
echo "  assessment_topic:  $(run_query "$TARGET" 'SELECT COUNT(*) FROM assessment_topic')"

# FK sanity check: every source assessment's offering/term/subject/user
# must already exist in the target. We verified manually that 82 offerings,
# 25 terms, 17 subjects, and 474 users line up perfectly between the two
# DBs, but re-check here so a drift mid-restore aborts before we write.
echo ""
echo "== FK validation =="
ORPHANS=$(run_query "$TARGET" "
    SELECT 'offerings:' AS k, COUNT(*) FROM $SOURCE.assessments a LEFT JOIN $TARGET.offerings o ON o.id=a.offering_id WHERE o.id IS NULL
    UNION SELECT 'terms:', COUNT(*) FROM $SOURCE.assessments a LEFT JOIN $TARGET.terms t ON t.id=a.term_id WHERE t.id IS NULL
    UNION SELECT 'subjects:', COUNT(*) FROM $SOURCE.assessments a LEFT JOIN $TARGET.subjects s ON s.id=a.subject_id WHERE s.id IS NULL
    UNION SELECT 'score_users:', COUNT(*) FROM $SOURCE.assessment_scores ss LEFT JOIN $TARGET.users u ON u.id=ss.user_id WHERE u.id IS NULL
")
echo "$ORPHANS" | sed 's/^/  orphan /'
TOTAL_ORPHANS=$(echo "$ORPHANS" | awk '{s+=$2} END {print s}')
if [[ "$TOTAL_ORPHANS" != "0" ]]; then
    echo ""
    echo "ABORT: $TOTAL_ORPHANS orphan FK references — would create dangling rows."
    exit 1
fi

if [[ "$APPLY" != "--apply" ]]; then
    echo ""
    echo "Dry-run complete. Re-run with --apply to write."
    exit 0
fi

echo ""
echo "== Applying =="

mysql "$TARGET" <<SQL
START TRANSACTION;

-- 1. Replace placeholder school with the real one, preserving id=1.
UPDATE schools dst
JOIN $SOURCE.schools src ON src.id = dst.id
SET dst.name = src.name,
    dst.motto = src.motto,
    dst.address = src.address,
    dst.logo_path = src.logo_path,
    dst.timezone = src.timezone,
    dst.kolibri_facility_id = src.kolibri_facility_id;

-- 2. Sync the per-type defaults (default_max_score) from the source.
UPDATE assessment_types dst
JOIN $SOURCE.assessment_types src ON src.id = dst.id
SET dst.weight = src.weight,
    dst.default_max_score = src.default_max_score,
    dst.display_order = src.display_order;

-- 3. Copy assessments + scores + topic associations. Column lists are
--    explicit because the source schema may carry the temporary
--    legacy_id/legacy_type cleanup columns that the target doesn't.
--    INSERT IGNORE so re-running is safe; a partial prior restore
--    won't double-insert.
INSERT IGNORE INTO assessments
    (id, assessment_type_id, subject_id, offering_id, term_id, date, name, info, max_score, confirmed, created_at, updated_at)
SELECT
    id, assessment_type_id, subject_id, offering_id, term_id, date, name, info, max_score, confirmed, created_at, updated_at
FROM $SOURCE.assessments;

INSERT IGNORE INTO assessment_scores
SELECT * FROM $SOURCE.assessment_scores;

INSERT IGNORE INTO assessment_topic
SELECT * FROM $SOURCE.assessment_topic;

COMMIT;
SQL

echo ""
echo "== Target: $TARGET (after) =="
echo "  schools (real name):   $(run_query "$TARGET" 'SELECT name FROM schools LIMIT 1')"
echo "  assessment_types:      $(run_query "$TARGET" 'SELECT COUNT(*) FROM assessment_types')"
echo "  assessments:           $(run_query "$TARGET" 'SELECT COUNT(*) FROM assessments')"
echo "  assessment_scores:     $(run_query "$TARGET" 'SELECT COUNT(*) FROM assessment_scores')"
echo "  assessment_topic:      $(run_query "$TARGET" 'SELECT COUNT(*) FROM assessment_topic')"
echo ""
echo "Done."
