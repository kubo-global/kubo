<?php

namespace Database\Seeders;

use App\Models\School;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Database\Seeder;
use KuboKolibri\Models\CurriculumMap;

/**
 * Seeds example curriculum mappings using real Khan Academy content
 * from the Kolibri CBSE India channel (Early Math).
 *
 * Channel: Khan Academy (English - CBSE India Curriculum)
 * Channel ID: 2fd54ca47a8f59c99fcefaaa3894c19e
 */
class KolibriContentSeeder extends Seeder
{
    private const CHANNEL_ID = '2fd54ca47a8f59c99fcefaaa3894c19e';

    public function run(): void
    {
        $school = School::firstOrCreate(
            ['name' => 'Default School'],
            ['motto' => 'Excellence in Education', 'timezone' => 'Africa/Lagos']
        );

        // Create Math subject if it doesn't exist
        $math = Subject::firstOrCreate(['name' => 'Mathematics']);

        // Create topics matching the Khan Academy Early Math structure
        $counting = Topic::firstOrCreate(['name' => 'Counting', 'subject_id' => $math->id]);
        $addSub = Topic::firstOrCreate(['name' => 'Addition and Subtraction', 'subject_id' => $math->id]);

        // =====================================================================
        // Counting — exercises and videos
        // =====================================================================
        $countingContent = [
            // Videos
            ['node' => 'bee740d7a210545ab047b58fb61b9420', 'kind' => 'video',    'order' => 1],  // Counting with small numbers
            ['node' => 'd9a995b0e23c536eb097fb696593c151', 'kind' => 'video',    'order' => 2],  // Counting in order
            ['node' => 'ad4c104a895d52aab8057652116a4697', 'kind' => 'video',    'order' => 3],  // Number grid
            ['node' => '9757286215535f04ba982eb8657e33e1', 'kind' => 'video',    'order' => 4],  // Counting objects 1
            ['node' => 'd61ad48615075daebf522fc945233156', 'kind' => 'video',    'order' => 5],  // Counting objects 2
            ['node' => 'c6751283222654a08e1e7d6ca0bf8cb9', 'kind' => 'video',    'order' => 6],  // Counting in pictures
            // Exercises (progressive difficulty)
            ['node' => '2039e90031405286b021b82a713cb880', 'kind' => 'exercise', 'order' => 10], // Count with small numbers
            ['node' => '8bcf3959f18f57c38d64615254fe3758', 'kind' => 'exercise', 'order' => 11], // Count in order
            ['node' => 'ace96c08e0295776a248a35df51d2742', 'kind' => 'exercise', 'order' => 12], // Count in pictures
            ['node' => '8c3d04ac301f56899017ff379340256b', 'kind' => 'exercise', 'order' => 13], // Count objects 1
            ['node' => '5e876dc3c95e5e6dab5e54558f3058de', 'kind' => 'exercise', 'order' => 14], // Numbers to 100
            ['node' => '8908f744ad8f55b29f3c24cb7e974583', 'kind' => 'exercise', 'order' => 15], // Missing numbers
            ['node' => '7edf4abae4f15ad2baa0b3e677a9f6c6', 'kind' => 'exercise', 'order' => 16], // Compare numbers of objects 1
            ['node' => '9d2876e0c4af5c17aed82fd5e0ea0b92', 'kind' => 'exercise', 'order' => 17], // Comparing numbers to 10
            ['node' => '56a519c79bc15bdc8345ebc90ec9bf0b', 'kind' => 'exercise', 'order' => 18], // Find 1 more or 1 less
        ];

        // =====================================================================
        // Addition and Subtraction — exercises and videos
        // =====================================================================
        $addSubContent = [
            // Videos
            ['node' => '7da76df5199a57eabf43b95cd7c175b3', 'kind' => 'video',    'order' => 1],  // Intro to addition
            ['node' => '8ba2a6362cec57208f70f31f12f07fca', 'kind' => 'video',    'order' => 2],  // Intro to subtraction
            ['node' => '46d958f23ee850e680765891e17adac5', 'kind' => 'video',    'order' => 3],  // Addition word problems within 10
            ['node' => '42fa118b47b2598db0e290cc2df21e48', 'kind' => 'video',    'order' => 4],  // Subtraction word problems within 10
            ['node' => '879c7dea9cd858618e89779f685d2478', 'kind' => 'video',    'order' => 5],  // Adding to 10
            ['node' => 'a340a56059cc573b95610d2c32828f50', 'kind' => 'video',    'order' => 6],  // Relating addition and subtraction
            // Exercises (progressive difficulty)
            ['node' => '27aa4bd211f7555a8f49202ca835819d', 'kind' => 'exercise', 'order' => 10], // Add within 5
            ['node' => 'd8586972312d558e8e245c75f3aa4400', 'kind' => 'exercise', 'order' => 11], // Add within 10
            ['node' => '995e4b649447575a86a25390154e193c', 'kind' => 'exercise', 'order' => 12], // Subtract within 5
            ['node' => 'c18623de835e56acb68aa3090516f058', 'kind' => 'exercise', 'order' => 13], // Subtract within 10
            ['node' => 'ccd7fc7c75495a77a7c9e036384bfa8b', 'kind' => 'exercise', 'order' => 14], // Making 5
            ['node' => '55f39fb5c7a950cd890759ed338ae631', 'kind' => 'exercise', 'order' => 15], // Make 10
            ['node' => 'c5ecf9c6313a51fc9a86315dd6d5d903', 'kind' => 'exercise', 'order' => 16], // Make 10 (grids and number bonds)
            ['node' => '4a3d05a957f45c62b8089bfaab350230', 'kind' => 'exercise', 'order' => 17], // Addition word problems within 10
            ['node' => 'bc3b9d781b305274a7839d9abed53a0c', 'kind' => 'exercise', 'order' => 18], // Subtraction word problems within 10
            ['node' => '3c1b42fd166458ae974e8bbb5d600b92', 'kind' => 'exercise', 'order' => 19], // Relate addition and subtraction
        ];

        foreach ($countingContent as $item) {
            CurriculumMap::firstOrCreate([
                'school_id' => $school->id,
                'subject_id' => $math->id,
                'topic_id' => $counting->id,
                'kolibri_node_id' => $item['node'],
            ], [
                'kolibri_channel_id' => self::CHANNEL_ID,
                'content_kind' => $item['kind'],
                'display_order' => $item['order'],
            ]);
        }

        foreach ($addSubContent as $item) {
            CurriculumMap::firstOrCreate([
                'school_id' => $school->id,
                'subject_id' => $math->id,
                'topic_id' => $addSub->id,
                'kolibri_node_id' => $item['node'],
            ], [
                'kolibri_channel_id' => self::CHANNEL_ID,
                'content_kind' => $item['kind'],
                'display_order' => $item['order'],
            ]);
        }
    }
}
