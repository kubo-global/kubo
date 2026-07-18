<?php

namespace Database\Seeders;

use App\Models\Grade;
use App\Models\School;
use App\Models\Subject;
use Illuminate\Database\Seeder;
use KuboKolibri\Models\CurriculumMap;
use App\Domain\Learning\Models\Skill;

/**
 * Seeds the Gambian primary math curriculum (Grades 1-6) mapped to Khan Academy
 * exercises available in Kolibri.
 *
 * Populates: skills, skill_edges, curriculum_maps, skill_content
 *
 * Kolibri channel: Khan Academy Math (c9d7f950ab6b5a1199e3d6c10d7f0103)
 */
class GambianCurriculumSeeder extends Seeder
{
    private const CHANNEL_ID = 'c9d7f950ab6b5a1199e3d6c10d7f0103';

    /**
     * Map Gambian curriculum grade number to KUBO grade name.
     * Adjust these values to match your database.
     */
    private const GRADE_NAMES = [
        1 => 'Grade 1',
        2 => 'Grade 2',
        3 => 'Grade 3',
        4 => 'Grade 4',
        5 => 'Grade 5',
        6 => 'Grade 6',
    ];

    /** Gambian school year: ~36 teaching weeks across 3 terms */
    private const WEEKS_PER_YEAR = 36;

    public function run(): void
    {
        $school = School::first();
        $math = Subject::where('name', 'Mathematics')->first();

        if (! $school || ! $math) {
            $this->command->warn('School or Mathematics subject not found. Skipping GambianCurriculumSeeder.');
            return;
        }

        // Resolve grade IDs
        $gradeIds = [];
        foreach (self::GRADE_NAMES as $num => $name) {
            $grade = Grade::where('name', $name)->first();
            $gradeIds[$num] = $grade?->id;
        }

        $curriculum = $this->curriculum();

        // Collect all created skills in order across all grades for cross-grade prerequisite edges
        $allSkills = [];

        foreach ($curriculum as $gradeNum => $chapters) {
            $level = 1;
            $chapterCount = count($chapters);
            $weeksPerChapter = max(1, floor(self::WEEKS_PER_YEAR / $chapterCount));

            foreach ($chapters as $i => $chapter) {
                $scheduledWeek = 1 + ($i * $weeksPerChapter);

                // Create the skill
                $skill = Skill::firstOrCreate(
                    [
                        'school_id' => $school->id,
                        'subject_id' => $math->id,
                        'name' => $chapter['name'],
                    ],
                    [
                        'grade_id' => $gradeIds[$gradeNum] ?? null,
                        'level' => $level,
                        'scheduled_week' => $scheduledWeek,
                    ]
                );

                // Update scheduled_week if skill already existed
                if (! $skill->wasRecentlyCreated) {
                    $skill->update(['scheduled_week' => $scheduledWeek]);
                }

                // Create curriculum_maps and link via skill_content
                $displayOrder = 1;
                foreach ($chapter['exercises'] as $nodeId) {
                    $map = CurriculumMap::firstOrCreate(
                        [
                            'school_id' => $school->id,
                            'subject_id' => $math->id,
                            'kolibri_node_id' => $nodeId,
                        ],
                        [
                            'kolibri_channel_id' => self::CHANNEL_ID,
                            'content_kind' => 'exercise',
                            'display_order' => $displayOrder,
                        ]
                    );

                    if (! $skill->content()->where('curriculum_map_id', $map->id)->exists()) {
                        $skill->content()->attach($map->id, ['role' => 'practice']);
                    }

                    $displayOrder++;
                }

                $allSkills[] = $skill;
                $level++;
            }
        }

        // Create prerequisite edges from the dependency graph
        $skillsByName = collect($allSkills)->keyBy('name');

        foreach ($this->prerequisites() as $skillName => $prereqNames) {
            $skill = $skillsByName->get($skillName);
            if (! $skill) {
                continue;
            }

            foreach ($prereqNames as $prereqName) {
                $prereq = $skillsByName->get($prereqName);
                if ($prereq && ! $skill->prerequisites()->where('prerequisite_id', $prereq->id)->exists()) {
                    $skill->prerequisites()->attach($prereq->id);
                }
            }
        }
    }

    /**
     * Prerequisite graph: skill → [skills it requires].
     * Skills not listed here have no prerequisites (entry points).
     */
    private function prerequisites(): array
    {
        return [
            // PRIMARY 1
            'Comparing and ordering sets' => ['Counting to 20'],
            'Reading and writing numbers to 50' => ['Counting to 20'],
            'Counting objects in a group' => ['Counting to 20'],
            'Grouping in units and tens' => ['Counting to 20', 'Counting objects in a group'],
            'Addition of whole numbers to 50' => ['Counting to 20', 'Grouping in units and tens'],
            'Subtraction' => ['Addition of whole numbers to 50'],
            'Simple mathematical sentences' => ['Addition of whole numbers to 50', 'Subtraction'],
            'Measurement of length' => ['Comparing and ordering sets'],

            // PRIMARY 2
            'Place value' => ['Grouping in units and tens', 'Reading and writing numbers to 50'],
            'Ordering numbers and number sequences' => ['Place value', 'Comparing and ordering sets'],
            'Addition and subtraction' => ['Addition of whole numbers to 50', 'Subtraction', 'Place value'],
            'Building multiplication tables' => ['Addition and subtraction'],
            'Fractions' => ['Grouping in units and tens'],
            'Simple mathematical statements' => ['Addition and subtraction', 'Simple mathematical sentences'],
            'Measuring length' => ['Measurement of length', 'Addition and subtraction'],
            'Telling the time' => ['Measurement of time'],
            'Everyday statistics' => ['Counting objects in a group'],

            // PRIMARY 3
            'Reading and writing numbers' => ['Place value'],
            'Addition and subtraction (3-digit)' => ['Addition and subtraction', 'Reading and writing numbers'],
            'Fractions (3rd grade)' => ['Fractions', 'Division'],
            'Place value (3rd grade)' => ['Place value', 'Reading and writing numbers'],
            'Multiplication' => ['Building multiplication tables'],
            'Division' => ['Multiplication'],
            'Money and shopping' => ['Addition and subtraction (3-digit)'],
            'Using inequality symbols' => ['Comparing and ordering sets', 'Addition and subtraction (3-digit)'],
            'Plane shapes (3rd grade)' => ['Plane shapes'],
            'Length (3rd grade)' => ['Measuring length'],
            'Capacity' => ['Comparing and ordering sets'],
            'Weight' => ['Comparing and ordering sets'],
            'Time (3rd grade)' => ['Telling the time'],
            'Everyday statistics (3rd grade)' => ['Everyday statistics'],

            // PRIMARY 4
            'Place value (4th grade)' => ['Place value (3rd grade)'],
            'Addition and subtraction (4th grade)' => ['Addition and subtraction (3-digit)'],
            'Multiplication and division' => ['Multiplication', 'Division'],
            'Fractions (4th grade)' => ['Fractions (3rd grade)'],
            'Decimals' => ['Fractions (4th grade)', 'Place value (4th grade)'],
            'Money and shopping (4th grade)' => ['Money and shopping', 'Decimals'],
            'Equations (4th grade)' => ['Addition and subtraction (4th grade)', 'Multiplication and division'],
            'Angles' => ['Plane shapes (3rd grade)'],
            'Perimeter and area' => ['Multiplication and division', 'Plane shapes (3rd grade)', 'Length (3rd grade)'],
            'Weight (4th grade)' => ['Weight'],
            'Time (4th grade)' => ['Time (3rd grade)'],
            'Everyday statistics (4th grade)' => ['Everyday statistics (3rd grade)'],

            // PRIMARY 5
            'Place value (5th grade)' => ['Decimals', 'Place value (4th grade)'],
            'Addition and subtraction of whole numbers' => ['Addition and subtraction (4th grade)'],
            'Fractions and decimals' => ['Fractions (4th grade)', 'Decimals'],
            'Percentages' => ['Fractions and decimals'],
            'Multiplication (5th grade)' => ['Multiplication and division', 'Fractions and decimals'],
            'Division (5th grade)' => ['Multiplication and division', 'Fractions and decimals'],
            'Rates and charges' => ['Multiplication (5th grade)', 'Division (5th grade)'],
            'Algebra' => ['Equations (4th grade)'],
            'Equations (5th grade)' => ['Algebra'],
            'Inequalities' => ['Equations (5th grade)'],
            'Perimeter (5th grade)' => ['Perimeter and area', 'Fractions and decimals'],
            'Area (5th grade)' => ['Perimeter and area', 'Multiplication (5th grade)'],
            'Time and time intervals' => ['Time (4th grade)', 'Addition and subtraction of whole numbers'],
            'Averages' => ['Addition and subtraction of whole numbers', 'Division (5th grade)'],

            // PRIMARY 6
            'Multiplication of decimal fractions' => ['Multiplication (5th grade)', 'Place value (5th grade)'],
            'Division of decimal fractions' => ['Division (5th grade)', 'Place value (5th grade)'],
            'Profit and loss per cent' => ['Percentages'],
            'Increase and decrease in percentages' => ['Profit and loss per cent'],
            'Ratio and proportion' => ['Fractions and decimals', 'Multiplication (5th grade)'],
            'Rates and charges (6th grade)' => ['Rates and charges', 'Ratio and proportion'],
            'Equations (6th grade)' => ['Equations (5th grade)'],
            'Constructing parallel and perpendicular lines' => ['Angles'],
            'Constructing plane shapes' => ['Constructing parallel and perpendicular lines', 'Angles'],
            'Area (6th grade)' => ['Area (5th grade)', 'Constructing plane shapes'],
            'Volume (6th grade)' => ['Area (6th grade)', 'Multiplication of decimal fractions'],
            'Everyday statistics (6th grade)' => ['Averages', 'Everyday statistics (4th grade)'],
        ];
    }

    /**
     * Full Gambian primary math curriculum mapped to Khan Academy exercise node IDs.
     */
    private function curriculum(): array
    {
        return [
            // =================================================================
            // GRADE 1 — KA Kindergarten + KA 1st grade
            // =================================================================
            1 => [
                [
                    'name' => 'Counting to 20',
                    'exercises' => [
                        '708d0550f7cf58b49344fd07f8273952', // Count with small numbers
                        '9f21778dbfb75ce38577cf0f15fd911c', // Count in order
                        '6b29d3daec8158adad47da70f9941d9e', // Find 1 more or 1 less
                    ],
                ],
                [
                    'name' => 'Comparing and ordering sets',
                    'exercises' => [
                        '8271f67bdb7f51ecba8d5ce84b4d9901', // Compare numbers of objects 1
                        'ad439d58b19d5b9b80192b1671c7673a', // Comparing numbers to 10
                        '4e26c1c645d555eda3544905b1d133ab', // Compare numbers of objects 2
                    ],
                ],
                [
                    'name' => 'Reading and writing numbers to 50',
                    'exercises' => [
                        'a3051465e50e5e29ba172b7ed391ac95', // Missing numbers
                        'a2c6c17acd105e4ea6b93b748ebded84', // Numbers to 100
                    ],
                ],
                [
                    'name' => 'Counting objects in a group',
                    'exercises' => [
                        '0dd0475484295001aa4caae9f0fb0700', // Count in pictures
                        '90abd85a413d59e4b64a14e64fe216e0', // Count objects 1
                        '1b687c798592569db5504db211ab0939', // Count objects 2
                    ],
                ],
                [
                    'name' => 'Grouping in units and tens',
                    'exercises' => [
                        '746401c93c995dddb176903936245b6c', // Teen numbers
                        'a34bdeb048b452a2ade3843c2f613efc', // Count tens
                        '3ccb4b990b875c868442700536377400', // Groups of ten objects
                        '064f66598a21501e8667de338da47f04', // Tens and ones
                    ],
                ],
                [
                    'name' => 'Addition of whole numbers to 50',
                    'exercises' => [
                        '40394f1ccd315c079cde7960b9f4e71e', // Add within 5
                        '8a7dff207d6c528bbc856fae573c6c43', // Add within 10
                        '9ca5b9c06cbf5da28a1138353d2bf9b4', // Making 5
                        '706b2e7ffaf65ebc8f88e22cff74ef3c', // Make 10
                    ],
                ],
                [
                    'name' => 'Subtraction',
                    'exercises' => [
                        'd806f5cda7c859b6b0c9159c7bc8a1f2', // Subtract within 5
                        'a7863fba89b35e2d9cc6f3862b99e027', // Subtract within 10
                    ],
                ],
                [
                    'name' => 'Simple mathematical sentences',
                    'exercises' => [
                        '56205414f5e059e69d19ed4ed4caf0d8', // Addition word problems within 10
                        '118419015695569cabb559269621da57', // Subtraction word problems within 10
                    ],
                ],
                [
                    'name' => 'Plane shapes',
                    'exercises' => [
                        '8275a9c2496456109d07b342d3d855a9', // Name shapes 1
                        '408b6258f7f75f12858c620330e1bfd4', // Name shapes 2
                    ],
                ],
                [
                    'name' => 'Measurement of length',
                    'exercises' => [
                        '3f7c6673d77657f7935dbe2d8e6c33e8', // Compare size
                        '2bae4cf2fac752ccac4f4de27d56e464', // Compare length
                    ],
                ],
                [
                    'name' => 'Measurement of time',
                    'exercises' => [
                        '9ff4fb85ddce56d98b27671209c3254d', // Tell time to hour or half hour
                    ],
                ],
            ],

            // =================================================================
            // GRADE 2 — KA 1st + KA 2nd grade
            // =================================================================
            2 => [
                [
                    'name' => 'Place value',
                    'exercises' => [
                        '1c0d768b95525123834aac5378343254', // Numbers to 120
                        '3488b73e47a9554f81a85937327700f0', // 2-digit place value challenge
                        '3d4060bf6fa75ac78f6e6f631c955033', // Place value blocks within 1,000
                        '2564918388fd5fe5857647ac55ef9be5', // Identify the value of a digit
                    ],
                ],
                [
                    'name' => 'Ordering numbers and number sequences',
                    'exercises' => [
                        'ce4d368bf521522a915cb2703b3d159a', // Compare 2-digit numbers
                        '5593725c39b85e00802970c2ce91daf0', // Compare 2-digit numbers 2
                        '7d7b436573355742a9feb08f9519e415', // Skip-count by 5s
                        '66b657cc80cb56c59ce1620b71507dad', // Skip-count by 10s
                    ],
                ],
                [
                    'name' => 'Addition and subtraction',
                    'exercises' => [
                        'a277c638c65a55f3b098b471a10b48bb', // Add within 20
                        '9063df5219705fb3bdfa4a7fd7daee9f', // Subtract within 20
                        '0796be8a774a5f0cb0e4e3bf5a57d7ba', // Add within 100 using place value blocks
                        '5e0a7afa0db15f0e92578c1a09d7cab9', // Subtract within 100 using place value blocks
                    ],
                ],
                [
                    'name' => 'Building multiplication tables',
                    'exercises' => [
                        'a46c6f5ee02f5b3492f2e1bbc6ef89cb', // Adding with arrays
                        '64ea741a5c1a59c39bdc709d9bf745e2', // Array word problems
                    ],
                ],
                [
                    'name' => 'Fractions',
                    'exercises' => [
                        '31f1529a9a44525b8f65060013a32c55', // Halves
                        'b2cdf5ac2b4051698c977ca4bf95296f', // Fourths
                    ],
                ],
                [
                    'name' => 'Simple mathematical statements',
                    'exercises' => [
                        '3be8b57ac2fd5fd1afd6033204d3379d', // Equal sign
                        '10a9bae46fd052e89c0d55a755f86984', // Find missing number (add and subtract within 20)
                    ],
                ],
                [
                    'name' => 'Measuring length',
                    'exercises' => [
                        '4157b586938252eea3732a6c127458f7', // Order by length
                        '6bdfa7b8549f5bf1801ccd8a874d42a9', // Units of length (cm, m)
                        '40c40a078a855a60a0d43180fd6c5a15', // Measure lengths (cm, m)
                    ],
                ],
                [
                    'name' => 'Telling the time',
                    'exercises' => [
                        '9938f259f5155a23a9f00e796b06aed2', // Telling time on a number line
                        '47cc371e4f755d1c913cbab8a9ea7adf', // Telling time on a clock
                    ],
                ],
                [
                    'name' => 'Everyday statistics',
                    'exercises' => [
                        'f94aee98a78d5af095310a2b52a826ec', // Make picture graphs
                        '7e3e4ec40c7f58c5ab259f40fdedb591', // Solve problems with picture graphs
                    ],
                ],
            ],

            // =================================================================
            // GRADE 3 — KA 2nd + KA 3rd grade
            // =================================================================
            3 => [
                [
                    'name' => 'Reading and writing numbers',
                    'exercises' => [
                        '8793b19e1bf45cb1959f0e78de9037d1', // Write numbers in number and expanded form
                        '58358f44cb8b500fa9e7a3f861c03552', // Write numbers in different forms
                        '5c0259095efe5af7ab839792cbd97588', // Hundreds, tens, and ones
                    ],
                ],
                [
                    'name' => 'Addition and subtraction (3-digit)',
                    'exercises' => [
                        'fb6a1203469558abbb20325dc932cbba', // Add using groups of 10 and 100
                        '5dfab46f68e75aa98a1e9b6539ebc84d', // Break apart 3-digit addition problems
                        'ca3be62fbf675b9194a033f77709ef3a', // Subtract within 1,000 using place value blocks
                        '13626e4ce8f653e1b5e27811b3fc0c06', // Addition word problems within 100
                        'dc64cf52f15d5c87b7ded2d34d6ad206', // Subtraction word problems within 100
                    ],
                ],
                [
                    'name' => 'Fractions (3rd grade)',
                    'exercises' => [
                        '63fcecddd4d65425a7d33e07fd23bd86', // Cut shapes into equal parts
                        '48b358c518ba589c84fb584d1c85352b', // Identify unit fractions
                        '9cac6c7a73e750deac852cc0bd2dfd9b', // Recognize fractions
                        '6350301da3ff516aa25e8cbf53bef839', // Fractions on the number line
                    ],
                ],
                [
                    'name' => 'Place value (3rd grade)',
                    'exercises' => [
                        'fd1a2032117456d9be7ecd82cc94e614', // Regroup whole numbers
                        '1617d86d19d75028b6e40aba019aeca0', // Compare 3-digit numbers
                    ],
                ],
                [
                    'name' => 'Multiplication',
                    'exercises' => [
                        '5874e70e4d3d56ae9ddabb13c7268519', // Equal groups
                        'b8f14ad976cf54e086692b427802620d', // Understand equal groups as multiplication
                        '69cb3db0deee51a49a048046bf6d5b88', // Multiply by 2
                        'de1e0b7ca433574293436d352d85af9e', // Multiply by 5
                        '483e90d1a66b5e7fbc5b42981a130f7c', // Multiply by 10
                        '6963cfbad5aa5abcae5b1d683c4940ed', // Basic multiplication
                    ],
                ],
                [
                    'name' => 'Division',
                    'exercises' => [
                        '77f51a31469b562e8dde6a61cb55151b', // Division with groups of objects
                        '4b496ec8c102500ebd9a5c6bec488e1a', // Division with arrays
                        'a5d1df0bf08f561d9a3d2a233f3bd4a7', // Divide by 2
                        '29c6b2bb104d54c69527130b2b470916', // Divide by 5
                        'ed4516e9a5845859901cb9bbe4ff2786', // Basic division
                    ],
                ],
                [
                    'name' => 'Money and shopping',
                    'exercises' => [
                        '2de70d69854d547a8bc16e3f3b7f7453', // Identify the value of US coins and dollars
                        'b9a34c4ea6ab5523921f9c21a8c069e1', // Count money (U.S.)
                        '70bc63ae1aba5f4898249c80d14989b0', // Money word problems (U.S.)
                    ],
                ],
                [
                    'name' => 'Using inequality symbols',
                    'exercises' => [
                        '3a4d47a9f4b8551aae3f6ebf40ddfde4', // Patterns with even and odd
                    ],
                ],
                [
                    'name' => 'Plane shapes (3rd grade)',
                    'exercises' => [
                        '40883365bdeb5c21a14826366e31b7ef', // Identify quadrilaterals
                        '30da899462de5dfa84f1490dc809e1f2', // Classify quadrilaterals
                    ],
                ],
                [
                    'name' => 'Length (3rd grade)',
                    'exercises' => [
                        'eae07f4685e75020beeb963519cdc017', // Units of length (inch, ft)
                        '736eb65d98ab50beb928a8dd7d4ce14b', // Measure length in different units
                    ],
                ],
                [
                    'name' => 'Capacity',
                    'exercises' => [
                        '9cbb0dbdd5205822a14516bfb8463fda', // Estimate volume (milliliters and liters)
                    ],
                ],
                [
                    'name' => 'Weight',
                    'exercises' => [
                        '784a3e0d273155fa8cb54d083b5775ec', // Estimate mass (grams and kilograms)
                    ],
                ],
                [
                    'name' => 'Time (3rd grade)',
                    'exercises' => [
                        'deedd94fe6935f5595060f1f83d91ed0', // Tell time to the nearest minute
                        '397c6f8e007b5c3191d903b9b7aeea70', // Time differences (within 60 minutes)
                    ],
                ],
                [
                    'name' => 'Everyday statistics (3rd grade)',
                    'exercises' => [
                        '62f3a066054f5443a0fd46c8bfc76151', // Create bar graphs
                        '91040ae8e3fa59d09d36e0afcc1e35b0', // Read bar graphs
                    ],
                ],
            ],

            // =================================================================
            // GRADE 4 — KA 3rd + KA 4th grade
            // =================================================================
            4 => [
                [
                    'name' => 'Place value (4th grade)',
                    'exercises' => [
                        '7d75e31d948a5bd2b211290aa2ae538e', // Place value blocks
                        '506cacac01cd5c839889153b6ba58737', // Place value tables
                        '2677e915c7485d2ea6e2efcfa0220c3c', // Identify value of a digit
                        '8f73c1717d6d5893b9844dc9eae1c107', // Writing whole numbers in expanded form
                    ],
                ],
                [
                    'name' => 'Addition and subtraction (4th grade)',
                    'exercises' => [
                        '4cd48c58e196547f8f95655cfe407067', // Multi-digit addition
                        'd4f03a4235ec5ff5a90ff72291f7c861', // Multi-digit subtraction
                        'd991959371db55c196bb02d795a3c079', // Round whole numbers
                    ],
                ],
                [
                    'name' => 'Multiplication and division',
                    'exercises' => [
                        '930d33a2834558639c3a545a61430ca3', // Multiply with regrouping
                        '8a4d1b387fa45693b0d445bb7c3d1fce', // Multiply 2-digit numbers
                        '6ff1c18ceca65ee4beff85939677af8c', // Divide with remainders (2-digit by 1-digit)
                        '7d02771b6bc2530286f4eb7aa03f58a8', // Divide multi-digit numbers by 2, 3, 4, and 5
                    ],
                ],
                [
                    'name' => 'Fractions (4th grade)',
                    'exercises' => [
                        'd255ea4b35585df4a7751630da709f13', // Equivalent fractions
                        'b034aa77c7cd5d398e9512a59df266f7', // Common denominators
                        'cbfc0384d9d5509e8bc475a7a2dc4d6b', // Add fractions with common denominators
                        '9802d95abfe55db58dcd998bf06b159f', // Subtract fractions with common denominators
                        '85c53c7c335c5b9290056f9117129db2', // Write mixed numbers and improper fractions
                    ],
                ],
                [
                    'name' => 'Decimals',
                    'exercises' => [
                        'd89f14f8d3be590d81561d45ab027377', // Write decimals and fractions shown on grids
                        '901668958f935719bc479e29e4cd9c98', // Write fractions as decimals
                        '75938cec1a155c5ca63c273cc46253d9', // Compare decimals (tenths and hundredths)
                    ],
                ],
                [
                    'name' => 'Money and shopping (4th grade)',
                    'exercises' => [
                        '5b10101f22e459cc9c4de2a1f107583f', // Convert money word problems
                    ],
                ],
                [
                    'name' => 'Equations (4th grade)',
                    'exercises' => [
                        '5ea5ff70d3d6551fa50afa1b72ad4cd8', // Find missing factors (1-digit multiplication)
                        'a1bc0eb0f22e5ff688dc68732120d83e', // Solve 2-step expressions
                    ],
                ],
                [
                    'name' => 'Angles',
                    'exercises' => [
                        'a69d61c2788759bb96f4a003132c127a', // Angle basics
                        'a0c5e18b628e52d1b4c3c79972cbf45c', // Types of angles by measure
                        'd2b6ce871df951c7b976e4a5b44e7d55', // Measure angles
                    ],
                ],
                [
                    'name' => 'Perimeter and area',
                    'exercises' => [
                        '5fd3cbc4c821517e8ed5575925e20cdc', // Find perimeter by counting units
                        '60b08e4bdc6a50a59b83b23b05decdcc', // Find perimeter when given side lengths
                        'a9dfccbbf13d587ea9e76bfebe6518be', // Area of rectangles
                        'be9ba8a179ec59e2b686bde5bc54789a', // Area & perimeter of rectangles word problems
                    ],
                ],
                [
                    'name' => 'Weight (4th grade)',
                    'exercises' => [
                        '5f23687f18ec5702b5bda36a8b7706f3', // Estimate mass (grams and kilograms)
                    ],
                ],
                [
                    'name' => 'Time (4th grade)',
                    'exercises' => [
                        'b3c4138d6f195771a9ed54dc4b86f448', // Time differences
                        '572b99c9030859f68b623096b7bcecd0', // Telling time word problems
                    ],
                ],
                [
                    'name' => 'Everyday statistics (4th grade)',
                    'exercises' => [
                        '62f3a066054f5443a0fd46c8bfc76151', // Create bar graphs
                        '910deb1578b65524a2d97dabb108f41b', // Read bar graphs (2-step problems)
                    ],
                ],
            ],

            // =================================================================
            // GRADE 5 — KA 4th + KA 5th grade
            // =================================================================
            5 => [
                [
                    'name' => 'Place value (5th grade)',
                    'exercises' => [
                        '697f3ad28fb65c6faa8912dda9cf7186', // Place value names
                        '4ddca9830fbd55629fabfec0cab93ada', // Value of a digit
                        'a516810c38a95fc2b3599660982f94b0', // Decimals in expanded form
                    ],
                ],
                [
                    'name' => 'Addition and subtraction of whole numbers',
                    'exercises' => [
                        '8780110c096f52b29fc42ce3181d5974', // Estimate multi-digit multiplication
                        '8e7d8deaa9835b0c9783575bc487d703', // Multi-digit multiplication
                        'bf57f04803675125af662db5e06e15e4', // Division by 2-digits
                    ],
                ],
                [
                    'name' => 'Fractions and decimals',
                    'exercises' => [
                        '5c6cce933007587c8c8960edf84dc9a3', // Add fractions with unlike denominators
                        'cddd0dc20a6a5f83aaf06f8661823a22', // Subtracting fractions with unlike denominators
                        '6f82ae2d91ea5bceb11c527285a50c4e', // Add and subtract fractions
                        'cc2fff1810375c618d72cadb8920c4d9', // Adding decimals (tenths)
                        'c602a594a6055f2bba518a744b9a0a8e', // Subtracting decimals (tenths)
                    ],
                ],
                [
                    'name' => 'Percentages',
                    'exercises' => [
                        '08db9a0c6c77597583d7682671e0cd6d', // Intro to percents
                        '1fbe2f6d21ef5e3eba010ad0a8dce3e6', // Find percents visually
                        'f60df569e6e15a02be8ec85226d0941e', // Finding percents
                    ],
                ],
                [
                    'name' => 'Multiplication (5th grade)',
                    'exercises' => [
                        'a72e1c62653b585095a2603f7dfe4524', // Multiplying fractions
                        'c4cf2e590fb055bf8cd36e6376e5189c', // Multiply mixed numbers
                        '5ebb9ec26be455b3a425746c36a813f6', // Multiply whole numbers and decimals
                    ],
                ],
                [
                    'name' => 'Division (5th grade)',
                    'exercises' => [
                        '569402bcf2885c9ba2fd0860057e5d1b', // Dividing unit fractions by whole numbers
                        'ab77317e05c35b669c51c115cc762a24', // Dividing whole numbers by unit fractions
                        '75a00fe2d2de510bbdd4873b30768a5e', // Divide decimals by whole numbers
                    ],
                ],
                [
                    'name' => 'Rates and charges',
                    'exercises' => [
                        '696de82740e1575688b86a41852fd2c1', // Unit rates
                        'dbe7deaae09654a7a01e7937c9c4dfdf', // Rate problems
                    ],
                ],
                [
                    'name' => 'Algebra',
                    'exercises' => [
                        'd5ece4bce5415a1f8ec04afdc407d698', // Evaluate expressions with parentheses
                        '4c62457cdea15aa5adcf92475926557f', // Translate expressions with parentheses
                    ],
                ],
                [
                    'name' => 'Equations (5th grade)',
                    'exercises' => [
                        'ee047695d71557f6872b9e0851b4be96', // Testing solutions to equations
                        '5fd09093505b566bbc40a683fc37c2bd', // One-step addition & subtraction equations
                    ],
                ],
                [
                    'name' => 'Inequalities',
                    'exercises' => [
                        'b5538f41cf7d5c92b2d35d358a9da611', // Testing solutions to inequalities (basic)
                        '7baa64e0c943540dae1e3848ea83be5a', // Graphing basic inequalities
                    ],
                ],
                [
                    'name' => 'Perimeter (5th grade)',
                    'exercises' => [
                        '67f7bfff93af5741b198ca0762f39f49', // Area of rectangles with fraction side lengths
                    ],
                ],
                [
                    'name' => 'Area (5th grade)',
                    'exercises' => [
                        'e448b0fb5d54529e9cfc9edd4afe8610', // Volume of rectangular prisms
                    ],
                ],
                [
                    'name' => 'Time and time intervals',
                    'exercises' => [
                        '77ceeb863a995639b452bff35c20899c', // Convert units of time
                    ],
                ],
                [
                    'name' => 'Averages',
                    'exercises' => [
                        '8884a78278cb516696228a93d190382b', // Calculating the mean
                        '748ba206141755fc9f316921fb42f7aa', // Calculating the median
                    ],
                ],
            ],

            // =================================================================
            // GRADE 6 — KA 5th + KA 6th grade
            // =================================================================
            6 => [
                [
                    'name' => 'Multiplication of decimal fractions',
                    'exercises' => [
                        '9898e589f0d359b68c0673fa72cbbd4c', // Multiply decimals tenths
                        'bb2db450c9365efcbe88ef4089695391', // Multiply decimals (1&2-digit factors)
                    ],
                ],
                [
                    'name' => 'Division of decimal fractions',
                    'exercises' => [
                        '33d3021e405c5858a99b7c53f69003c2', // Divide whole numbers to get a decimal (1-digit divisors)
                        'f30e6e736aa85bb8af0fcc18614d9f97', // Dividing decimals: hundredths
                    ],
                ],
                [
                    'name' => 'Profit and loss per cent',
                    'exercises' => [
                        'f60df569e6e15a02be8ec85226d0941e', // Finding percents
                        'e231636949b8555e8c053d06cb79571a', // Percent word problems
                    ],
                ],
                [
                    'name' => 'Increase and decrease in percentages',
                    'exercises' => [
                        'e231636949b8555e8c053d06cb79571a', // Percent word problems
                    ],
                ],
                [
                    'name' => 'Ratio and proportion',
                    'exercises' => [
                        '8a1589e8e7e95d11a82fdfaf940b16f5', // Basic ratios
                        'b892f0eb7c0e583f84e5a12680436e5e', // Equivalent ratios
                        '30b29d219f30518b8b85d48fd32fcf0e', // Equivalent ratio word problems
                    ],
                ],
                [
                    'name' => 'Rates and charges (6th grade)',
                    'exercises' => [
                        '696de82740e1575688b86a41852fd2c1', // Unit rates
                        'dbe7deaae09654a7a01e7937c9c4dfdf', // Rate problems
                        '4923d6e866de5996b9990020b82415ba', // Comparing rates
                    ],
                ],
                [
                    'name' => 'Equations (6th grade)',
                    'exercises' => [
                        '5fd09093505b566bbc40a683fc37c2bd', // One-step addition & subtraction equations
                        '9edf6e8afc095fe9ba3bc57871d87d1f', // One-step multiplication & division equations
                        'a0ef275ffba85669907582ba1ae3ad71', // Model with one-step equations and solve
                    ],
                ],
                [
                    'name' => 'Constructing parallel and perpendicular lines',
                    'exercises' => [
                        'afa5272e9161556d8533af711d99ecb9', // Identify parallel and perpendicular lines
                        'fe8606f027d55021832456f82cb35907', // Draw parallel and perpendicular lines
                    ],
                ],
                [
                    'name' => 'Constructing plane shapes',
                    'exercises' => [
                        '135e8cf3e2bc5722a0f75476bd8c685b', // Classify triangles by angles
                        '6789091b69125af0854b489a838f968f', // Classify triangles by side lengths
                    ],
                ],
                [
                    'name' => 'Area (6th grade)',
                    'exercises' => [
                        '9394900beec456219fbb56e31a56c4f1', // Area of parallelograms
                        '50cec4dd646d50ca9d8b3cb24c77f993', // Area of triangles
                    ],
                ],
                [
                    'name' => 'Volume (6th grade)',
                    'exercises' => [
                        'e448b0fb5d54529e9cfc9edd4afe8610', // Volume of rectangular prisms
                        '7507ef740bc35b578dd3d0b85945e2cb', // Volume word problems
                    ],
                ],
                [
                    'name' => 'Everyday statistics (6th grade)',
                    'exercises' => [
                        '8884a78278cb516696228a93d190382b', // Calculating the mean
                        '748ba206141755fc9f316921fb42f7aa', // Calculating the median
                        'ac339d7f1a94558987e1e8021b360ff6', // Create histograms
                    ],
                ],
            ],
        ];
    }
}
