#!/bin/bash
set -e

echo "========================================"
echo "  KUBO School Platform — Setup"
echo "========================================"
echo ""

# Check dependencies
command -v php >/dev/null 2>&1 || { echo "PHP is required but not installed."; exit 1; }
command -v mysql >/dev/null 2>&1 || { echo "MySQL is required but not installed."; exit 1; }
command -v composer >/dev/null 2>&1 || { echo "Composer is required but not installed."; exit 1; }
command -v npm >/dev/null 2>&1 || { echo "NPM is required but not installed."; exit 1; }

# Create .env if missing
if [ ! -f .env ]; then
    cp .env.production.example .env
    php artisan key:generate
    echo "Created .env — please edit it with your database credentials."
    echo ""
fi

# Install dependencies
echo "Installing PHP dependencies..."
composer install --no-dev --optimize-autoloader 2>&1 | tail -3

echo "Installing frontend dependencies..."
npm ci 2>&1 | tail -3

echo "Building frontend assets..."
npm run build 2>&1 | tail -3

# Database
echo ""
echo "Running migrations..."
php artisan migrate --force

echo ""
echo "Creating default school setup..."
php artisan tinker --execute="
if (\App\Models\School::count() === 0) {
    \$name = readline('School name: ');
    \App\Models\School::create(['name' => \$name ?: 'My School']);
    echo 'School created.' . PHP_EOL;
} else {
    echo 'School already exists: ' . \App\Models\School::first()->name . PHP_EOL;
}

\$school = \App\Models\School::first();
if (\$school->assessmentTypes()->count() === 0) {
    \$school->assessmentTypes()->create(['name' => 'Test', 'weight' => 0.25, 'display_order' => 1]);
    \$school->assessmentTypes()->create(['name' => 'Exam', 'weight' => 0.75, 'display_order' => 2]);
    echo 'Assessment types created (Test 25%, Exam 75%).' . PHP_EOL;
}

if (\$school->gradingScales()->count() === 0) {
    \$school->gradingScales()->createMany([
        ['grade' => 'A', 'min_score' => 70, 'max_score' => 100],
        ['grade' => 'B', 'min_score' => 60, 'max_score' => 69.99],
        ['grade' => 'C', 'min_score' => 50, 'max_score' => 59.99],
        ['grade' => 'D', 'min_score' => 40, 'max_score' => 49.99],
        ['grade' => 'E', 'min_score' => 30, 'max_score' => 39.99],
        ['grade' => 'F', 'min_score' => 0, 'max_score' => 29.99],
    ]);
    echo 'Grading scale created (A-F).' . PHP_EOL;
}
"

# Create admin user
echo ""
php artisan tinker --execute="
if (\App\Models\User::role('headmaster')->count() === 0) {
    echo 'Creating admin account...' . PHP_EOL;
    \$name = readline('Admin first name: ');
    \$last = readline('Admin last name: ');
    \$pass = readline('Password: ');
    \$user = \App\Models\User::create([
        'first_name' => \$name ?: 'Admin',
        'last_name' => \$last ?: 'User',
        'password' => bcrypt(\$pass ?: 'admin'),
    ]);
    \$user->syncRoles(['headmaster', 'admin', 'teacher']);
    echo 'Admin created. Log in at the login page.' . PHP_EOL;
} else {
    echo 'Admin already exists.' . PHP_EOL;
}
"

# Cache
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo ""
echo "========================================"
echo "  Setup complete!"
echo ""
echo "  Start the server:"
echo "    ./start.sh"
echo ""
echo "  Or for production (nginx + php-fpm):"
echo "    See docs/deployment.md"
echo "========================================"
