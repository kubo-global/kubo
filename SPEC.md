# KUBO School Platform Specification

Extracted from the current codebase for rewrite reference.

---

## 1. Database Schema

### Users & Profiles

**users** (STI base — Student and Teacher share this table)
| Column | Type | Notes |
|--------|------|-------|
| id | int PK | |
| first_name | string | ucfirst on read |
| last_name | string | ucfirst on read |
| email | string, nullable, unique | |
| password | string | Students: bcrypt(birth_date), Staff: bcrypt('secret') default |
| archived | boolean | Soft archive flag |
| remember_token | string, nullable | |
| timestamps | | |

**profiles**
| Column | Type | Notes |
|--------|------|-------|
| id | int PK | |
| user_id | FK→users, cascade | |
| birth_date | date, nullable | |
| gender | char, nullable | |
| tribe | string, nullable | |
| primary_phone | string, nullable | |
| secondary_phone | string, nullable | |
| address | text, nullable | |
| comment | text, nullable | |
| timestamps | | |

**contacts**
| Column | Type | Notes |
|--------|------|-------|
| id | int PK | |
| first_name | string, nullable | |
| last_name | string, nullable | |
| gender | char, nullable | |
| tribe | string, nullable | |
| primary_phone | string, nullable | |
| secondary_phone | string, nullable | |
| address | text, nullable | |
| email | string, nullable | |
| relation | string, nullable | e.g. "parent", "guardian" |
| comment | string, nullable | |
| timestamps | | |

**contact_user** (pivot)
| Column | Type |
|--------|------|
| contact_id | FK→contacts |
| user_id | FK→users |
| PK: (contact_id, user_id) | |

### School Structure

**schoolyears**
| Column | Type | Notes |
|--------|------|-------|
| id | int PK | |
| name | string | e.g. "2020 - 2021" |
| start | date | Default: Sept 1 |
| end | date | Default: June 30 next year |

**grades**
| Column | Type | Notes |
|--------|------|-------|
| id | int PK | |
| name | string, unique | e.g. "Nursery 1", "Primary 6" |

**offerings** (a class/grade instance for a specific school year)
| Column | Type | Notes |
|--------|------|-------|
| id | int PK | |
| schoolyear_id | FK→schoolyears | |
| grade_id | FK→grades | |
| activated | boolean, default false | |
| name | string, nullable | |
| timestamps | | |
| UNIQUE: (schoolyear_id, grade_id) | | |

**enrollments** (student ↔ offering)
| Column | Type |
|--------|------|
| id | int PK |
| user_id | FK→users |
| offering_id | FK→offerings |
| timestamps | |

**teacher_offering** (pivot — teacher ↔ offering)
| Column | Type | Notes |
|--------|------|-------|
| user_id | FK→users | |
| offering_id | FK→offerings | |
| principal | boolean, default false | Designates the "class teacher" |
| PK: (user_id, offering_id) | | |

### Curriculum

**subjects**
| Column | Type |
|--------|------|
| id | int PK |
| name | string |

**topics**
| Column | Type |
|--------|------|
| id | int PK |
| name | string |
| subject_id | FK→subjects |

**subject_term_offering** (which subjects are taught in which term for which offering)
| Column | Type |
|--------|------|
| id | int PK |
| subject_id | FK→subjects, cascade |
| term_id | FK→terms, cascade |
| offering_id | FK→offerings, cascade |

### Terms

**terms**
| Column | Type |
|--------|------|
| id | int PK |
| schoolyear_id | FK→schoolyears |
| name | string |
| start | date |
| end | date |

### Tests & Test Scores

**tests**
| Column | Type | Notes |
|--------|------|-------|
| id | int PK | |
| subject_id | FK→subjects | |
| offering_id | FK→offerings | |
| term_id | FK→terms | |
| date | date, nullable | |
| name | string, nullable | |
| info | string, nullable | Description |
| max_score | int, default 100 | |
| confirmed | boolean, default false | Whether scores are finalized |
| timestamps | | |

**test_scores**
| Column | Type | Notes |
|--------|------|-------|
| user_id | FK→users | |
| test_id | FK→tests | |
| score | int unsigned, nullable | Null when excused |
| excused | boolean, default false | Student was absent |
| timestamps | | |
| PK: (user_id, test_id) | | |

**test_topic** (pivot)
| Column | Type |
|--------|------|
| test_id | FK→tests |
| topic_id | FK→topics |
| PK: (test_id, topic_id) | |

### Exams & Exam Scores

**exams** — identical structure to tests
| Column | Type | Notes |
|--------|------|-------|
| id | int PK | |
| subject_id | FK→subjects | |
| offering_id | FK→offerings | |
| term_id | FK→terms | |
| date | date, nullable | |
| name | string, nullable | |
| info | string, nullable | |
| max_score | int | |
| confirmed | boolean, default false | |
| timestamps | | |

**exam_scores** — identical structure to test_scores
| Column | Type | Notes |
|--------|------|-------|
| user_id | FK→users | |
| exam_id | FK→exams | |
| score | int unsigned, nullable | |
| excused | boolean, default false | |
| timestamps | | |
| PK: (user_id, exam_id) | | |

**exam_topic** (pivot)
| Column | Type |
|--------|------|
| exam_id | FK→exams |
| topic_id | FK→topics |
| PK: (exam_id, topic_id) | |

### Health

**health_reports**
| Column | Type |
|--------|------|
| id | bigint PK |
| user_id | FK→users, cascade |
| general_condition | string, nullable |
| height_in_cm | int, nullable |
| weight_in_gram | int, nullable |
| teeth_condition | string, nullable |
| eyes_condition | string, nullable |
| ears_condition | string, nullable |
| hair_condition | string, nullable |
| nails_condition | string, nullable |
| wound_and_bruise_observations | string, nullable |
| worm_treatment_received | boolean, nullable |
| already_menstruated | boolean, nullable |
| hepatitis_a_vaccine_received | boolean, nullable |
| poliomyelitis_vaccine_received | boolean, nullable |
| tetanus_vaccine_received | boolean, nullable |
| yellow_fever_vaccine_received | boolean, nullable |
| timestamps | |

### Configuration

**school_parameters**
| Column | Type | Notes |
|--------|------|-------|
| key | string, unique | e.g. "testWeight", "examWeight" |
| value | text, nullable | Decimal between 0 and 1 |
| timestamps | | |

### Auth (Spatie Permission)

Standard Spatie tables: `permissions`, `roles`, `model_has_permissions`, `model_has_roles`, `role_has_permissions`.

---

## 2. Roles & Permissions

### Roles
| Role | Purpose |
|------|---------|
| headmaster | School leadership — sees all classes, all students |
| teacher | Sees own assigned classes only |
| caregiver | Health records access |
| admin | School administration — manages users, roles, school structure |
| student | No permissions (not used for login in current system) |

### Permissions
| Permission | Granted to |
|------------|-----------|
| view students | teacher |
| view all students | headmaster |
| view class | teacher |
| view all classes | headmaster |
| view medical records | caregiver |
| create new class | admin |
| add new user | admin |
| assign roles | admin |
| edit student details | headmaster |
| add student contact | headmaster |
| edit student contact | headmaster |
| add enrollment | headmaster |
| delete enrollment | headmaster |

### Route-Level Access Control
| Route group | Allowed roles |
|-------------|---------------|
| Students, Tests, Exams, Reports | headmaster, admin, teacher |
| Health reports | headmaster, admin, caregiver |
| Legacy admin (school structure CRUD) | admin |
| School management page | headmaster, admin |

### Role-Based Data Filtering
- **Teachers** see only their assigned offerings (filtered by `teacher_offering` pivot)
- **Headmasters** see all offerings for the school year
- **Student lists** are filtered: teachers see students in their classes, headmasters/admins see all

---

## 3. Core Business Logic

### 3.1 School Year & Term Selection

The system tracks which school year is "active" via session:
1. `Schoolyear::current()` — where `start <= now <= end`
2. `Schoolyear::latest()` — most recent by start date (fallback)
3. `Schoolyear::selected()` — from session, or falls back to `latest()`
4. Users can switch school year via `switchSchoolyear()` (stores in session)

Terms belong to a school year. `Term::current()` — where `start <= now`.

### 3.2 Score Entry (Tests & Exams)

Both tests and exams follow an identical 3-step wizard flow:

**Step 1 — Details:**
- Select offering (class), term, subject
- Enter assessment name, date, max score
- Subjects are loaded dynamically from `subject_term_offering` based on selected offering + term

**Step 2 — Scores:**
- List all enrolled students for the selected offering
- For each student: enter a numeric score OR mark as absent
- Validation: every student must have either a score or be marked absent

**Step 3 — Review & Confirm:**
- Display all scores with calculated percentages
- On confirm: stores data to session, redirects to controller `store()` which persists

**Score Constraints (enforced in model boot):**
- `score != null && excused == true` → exception (can't have score AND be excused)
- `score == null && excused == false` → exception (must have score OR be excused)
- `score > max_score` → exception

**Score Editing:**
- Existing tests/exams can be edited (name, date, all student scores)
- Same validation applies: each student needs score OR absent flag
- If marked absent: `score=null, excused=true`

**Deletion:**
- Tests/exams cascade-delete their scores
- Legacy controllers prevent deletion if scores exist; new controllers just cascade

### 3.3 Term Report Generation

**Algorithm — per student per term:**

1. **Inputs:** Enrollment (student + offering), Term
2. **Metadata:** Student info, school year, grade, principal teacher (from `teacher_offering` where `principal=true`)
3. **For each subject** in the offering's curriculum for this term:

   a. **Test results:**
   - Query all tests for (offering, term, subject) with this student's scores
   - Filter out excused tests (no score)
   - For each scored test: calculate `score / max_score`
   - `test_average = sum(percentages) / count * 100` (rounded)
   - `test_weighted = test_average * testWeight` (from `school_parameters`)
   - If no scored tests exist: `test_weighted = null`

   b. **Exam results:**
   - Same algorithm as tests, using `examWeight`

   c. **Subject total:**
   - `subject_total = test_weighted + exam_weighted`
   - If either is null → `subject_total = null` (incomplete data for this subject)

4. **Term total:** Sum of all non-null subject totals
5. **Term average:** Sum of non-null subject totals / count of subjects with non-null totals (rounded)

**Output:** PDF report (A5 paper) containing student info, per-subject breakdown (test avg, exam avg, subject total), term total, term average.

**Class report:** Iterates over all enrollments in an offering, generates individual report for each student, combines into one PDF.

### 3.4 Weighting System

Stored in `school_parameters` table:
- `testWeight` — decimal, how much tests count toward subject total (e.g. 0.4 = 40%)
- `examWeight` — decimal, how much exams count toward subject total (e.g. 0.6 = 60%)

These must be configured in the database. The system reads them at report generation time.

**Example calculation:**
- Student has tests averaging 85% and exams averaging 90%
- testWeight = 0.4, examWeight = 0.6
- Subject total = (85 * 0.4) + (90 * 0.6) = 34 + 54 = 88

### 3.5 Curriculum Management

Curriculum = which subjects are taught in which offering for which term.

- Managed via `subject_term_offering` pivot table
- When a subject is added to an offering, it's synced for ALL terms in that school year
- Deletion removes the subject from the offering across all terms
- The subjects available for test/exam creation are filtered by this curriculum mapping

### 3.6 Student Management

**Creating a student:**
- Required: first_name, last_name, offering_id, gender, birth_date
- Password defaults to `bcrypt(birth_date)`
- Creates User record + Profile record + Enrollment record
- Students can have multiple enrollments across school years (progression tracking)

**Student contact management:**
- Contacts are linked to students via `contact_user` pivot (many-to-many)
- Contact fields: name, gender, tribe, phone, address, relation, comment

**Student enrollment:**
- Students can be enrolled in additional offerings
- Enrollments can be deleted (with permission check)

### 3.7 Health Records

**Fields tracked:**
- General condition (free text)
- Physical measurements: height (cm), weight (grams)
- Body condition assessments: teeth, eyes, ears, hair, nails (free text each)
- Wound/bruise observations (free text)
- Booleans: worm treatment received, already menstruated
- Vaccine booleans: hepatitis A, poliomyelitis, tetanus, yellow fever

**Access:** headmaster, admin, caregiver roles only.

### 3.8 User Management

**Creating staff users:**
- Required: first_name, last_name
- Default password: `bcrypt('secret')`
- Profile created alongside
- Roles assigned separately via admin interface

**Password management:**
- Admin can reset any user's password to `bcrypt('secret')`
- Users can change their own password (requires current password verification)

**User deletion safeguards:**
- Cannot delete user with: enrollments, teacher-class assignments, test scores, exam scores, or contacts
- Profile is deleted along with user

**Role management:**
- Admin can toggle roles on/off for users
- Roles: headmaster, teacher, caregiver, admin, student

### 3.9 Teacher-Class Assignment

- Teachers are assigned to offerings via `teacher_offering` pivot
- One teacher per offering can be marked `principal=true` (class teacher / homeroom teacher)
- The principal teacher appears on term reports
- Teachers only see data for their assigned offerings

---

## 4. Route Map

### Authentication
| Method | Path | Action |
|--------|------|--------|
| GET | /login | Show login form (lists non-archived users with roles) |
| POST | /login | Authenticate (username field = user id) |
| GET | /logout | Logout |
| GET | /profile | Show authenticated user's profile |

### Dashboard
| Method | Path | Action | Roles |
|--------|------|--------|-------|
| GET | /dashboard | Main dashboard (schoolyear summary, student/teacher/offering counts) | auth |

### Students
| Method | Path | Action | Roles |
|--------|------|--------|-------|
| GET | /students | List students (filtered by role) | headmaster, admin, teacher |
| GET | /students/create | New student form | headmaster, admin |
| GET | /students/{student} | Student detail (profile, contacts, records, health tabs) | headmaster, admin, teacher |
| GET | /students/{student}/edit | Edit student | headmaster, admin |
| GET | /students/{student}/contacts/create | New contact form | headmaster, admin, teacher |
| GET | /students/{student}/contacts/{contact} | View contact | headmaster, admin, teacher |

### Tests
| Method | Path | Action | Roles |
|--------|------|--------|-------|
| GET | /reporting/test/grading | Test creation wizard (Livewire) | headmaster, admin, teacher |
| GET | /reporting/test/submit | Persist test from session | headmaster, admin, teacher |
| GET | /reporting/test/edit/{test} | Edit test scores | headmaster, admin, teacher |
| POST | /reporting/test/update/scores | Save edited scores | headmaster, admin, teacher |
| GET | /reporting/test/delete/{test} | Delete test | headmaster, admin, teacher |

### Exams
| Method | Path | Action | Roles |
|--------|------|--------|-------|
| GET | /reporting/exam/grading | Exam creation wizard (Livewire) | headmaster, admin, teacher |
| GET | /reporting/exam/submit | Persist exam from session | headmaster, admin, teacher |
| GET | /reporting/exam/edit/{exam} | Edit exam scores | headmaster, admin, teacher |
| POST | /reporting/exam/update/scores | Save edited scores | headmaster, admin, teacher |
| GET | /reporting/exam/delete/{exam} | Delete exam | headmaster, admin, teacher |

### Reports
| Method | Path | Action | Roles |
|--------|------|--------|-------|
| GET | /reporting/grades | Scorebook view (Livewire grades component) | headmaster, admin, teacher |
| GET | /termreports/overview | Term report generator form (Livewire) | headmaster, admin, teacher |
| GET | /print/termreport/{enrollmentId}/{termId} | Generate student PDF report | headmaster, admin, teacher |
| GET | /print/termreports/{schoolyearId}/{termId}/{gradeId} | Generate class PDF report | headmaster, admin, teacher |

### Scorebook
| Method | Path | Action | Roles |
|--------|------|--------|-------|
| GET | /scorebook/create | Score sheet view | headmaster, admin, teacher |

### Health
| Method | Path | Action | Roles |
|--------|------|--------|-------|
| GET | /health | List health reports (searchable) | headmaster, admin, caregiver |
| GET | /health/create/{student} | New health report form | headmaster, admin, caregiver |
| POST | /health/store | Save health report | headmaster, admin, caregiver |
| GET | /health/show/{healthReport} | View health report | headmaster, admin, caregiver |

### School Management
| Method | Path | Action | Roles |
|--------|------|--------|-------|
| GET | /schoolmanagement | School management dashboard | headmaster, admin |

### Legacy Admin
All under `/legacy-admin` prefix, `admin` role required.

| Resource | CRUD Operations |
|----------|----------------|
| Grades | index, create, store, edit, update, destroy |
| School Years | index, create, store, edit, update, destroy |
| Subjects | index, create, store, edit, update, destroy |
| Terms | index, create, store, edit, update, destroy |
| Offerings | index only (CRUD via API) |
| Curricula | index, create, store, destroy (destroys all terms for subject-offering pair) |
| Teacher-Class assignments | index, create, store, destroy |
| Users | index, create, store, edit, update, destroy, resetPassword |
| User Roles | index, update (toggle role) |

### API
| Method | Path | Action | Roles |
|--------|------|--------|-------|
| GET | /api/grades | List all grades | admin |
| GET | /api/offerings | List offerings (filter by schoolyear_id, grade_id) | admin |
| POST | /api/offerings | Create offering | admin |
| POST | /api/offerings/toggle | Toggle offering activated status | admin |
| DELETE | /api/offerings/{offering} | Delete offering | admin |

---

## 5. UI Components (Current Livewire)

### Multi-Step Wizards
**Test.php / Exam.php** — identical 3-step flows:
1. Details: dropdowns (offering→term→subject cascade), name, date, max_score
2. Scores: student list with score input + absent toggle per student
3. Overview: review table with percentages, back/confirm buttons

### List Components (all with search + pagination)
- **Students.php** — student list, filtered by role, searchable by name, 10/page
- **Healthreports.php** — health report list, searchable by student name, 10/page
- **Users.php** — staff list (admin/caregiver/headmaster/teacher), searchable, 10/page

### Grade Scorebook
**Grades.php** — offering/term/subject selection, displays grid of students × tests/exams with scores

### Student Detail
**LivewireUser.php** — tabbed view (profile, contacts, school records, health), inline editing toggle, enrollment creation for new students

### Contact Management
**Contact.php** — form component for viewing/creating/editing contacts, with inline editing toggle

### Term Report Generator
**Termreports.php** — schoolyear/term/grade selection, generates class PDF report

---

## 6. Known Issues / Quirks

1. **Term.current() bug** — checks `end <= now` instead of `end >= now`
2. **Session-based wizard flow** — test/exam data passes through session between Livewire component and controller store action (fragile, loses data on session expiry)
3. **ContactController@show uses dd()** — debug dump, unfinished
4. **Duplicate controller logic** — NewInterfaceControllers and WebControllers both handle students, tests, exams, reports with overlapping but different logic
5. **No form request validation** — most controllers validate inline or not at all (except LoginRequest)
6. **Date format inconsistency** — legacy controllers expect `d/m/Y`, new controllers use standard date input
7. **Default passwords** — students get `bcrypt(birth_date)`, staff get `bcrypt('secret')`
8. **Test/Exam are near-identical** — models, controllers, Livewire components, views are all copy-pasted with s/test/exam/
9. **N+1 queries in Grades component** — `getTestScore()` and `getExamScore()` query individually per student per test/exam
10. **No soft deletes** — user deletion is permanent, with manual cascade checks
11. **Reports model exists but appears unused** — term reports are generated on-the-fly from repositories, not stored
