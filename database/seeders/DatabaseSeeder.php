<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\ClassModel;
use App\Models\Subject;
use App\Models\Student;
use App\Models\Score;
use App\Models\GradeRule;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Clear existing data in correct order
        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');
        } else {
            DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        }

        Score::truncate();
        Student::truncate();
        DB::table('class_subject')->truncate();
        Subject::truncate();
        ClassModel::truncate();
        User::truncate();
        GradeRule::truncate();
        DB::table('permission_role')->truncate();
        Permission::truncate();
        Role::truncate();

        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON');
        } else {
            DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        }

        // 2. Create Roles
        $adminRole = Role::create([
            'name' => 'admin',
            'description' => 'System Administrator with full access',
            'is_system' => true
        ]);
        
        $teacherRole = Role::create([
            'name' => 'teacher',
            'description' => 'Teacher / Trainer who manages scores and students',
            'is_system' => true
        ]);
        
        $studentRole = Role::create([
            'name' => 'student',
            'description' => 'Student who can only view their own score information',
            'is_system' => true
        ]);

        // 3. Create Permissions
        $permissions = [
            // Users
            ['name' => 'View Users', 'slug' => 'view_users', 'description' => 'View user accounts list and profiles'],
            ['name' => 'Create Users', 'slug' => 'create_users', 'description' => 'Create new user accounts'],
            ['name' => 'Edit Users', 'slug' => 'edit_users', 'description' => 'Edit existing user accounts and profiles'],
            ['name' => 'Delete Users', 'slug' => 'delete_users', 'description' => 'Delete or suspend user accounts'],

            // Classes
            ['name' => 'View Classes', 'slug' => 'view_classes', 'description' => 'View list and details of classes'],
            ['name' => 'Create Classes', 'slug' => 'create_classes', 'description' => 'Create new classes'],
            ['name' => 'Edit Classes', 'slug' => 'edit_classes', 'description' => 'Edit class names and assigned subjects'],
            ['name' => 'Delete Classes', 'slug' => 'delete_classes', 'description' => 'Delete classes'],

            // Subjects
            ['name' => 'View Subjects', 'slug' => 'view_subjects', 'description' => 'View list and details of subjects'],
            ['name' => 'Create Subjects', 'slug' => 'create_subjects', 'description' => 'Create new subjects'],
            ['name' => 'Edit Subjects', 'slug' => 'edit_subjects', 'description' => 'Edit subject names and descriptions'],
            ['name' => 'Delete Subjects', 'slug' => 'delete_subjects', 'description' => 'Delete subjects'],

            // Students
            ['name' => 'View Students', 'slug' => 'view_students', 'description' => 'View student roster and full profiles'],
            ['name' => 'Create Students', 'slug' => 'create_students', 'description' => 'Register new student profiles and user logins'],
            ['name' => 'Edit Students', 'slug' => 'edit_students', 'description' => 'Update student bio data and attributes'],
            ['name' => 'Delete Students', 'slug' => 'delete_students', 'description' => 'Delete student profiles and credentials'],

            // Scores
            ['name' => 'View Scores', 'slug' => 'view_scores', 'description' => 'View class and student grades'],
            ['name' => 'Create Scores', 'slug' => 'create_scores', 'description' => 'Input or insert new score records'],
            ['name' => 'Edit Scores', 'slug' => 'edit_scores', 'description' => 'Update existing student scores'],
            ['name' => 'Delete Scores', 'slug' => 'delete_scores', 'description' => 'Clear or delete student scores'],

            // Custom
            ['name' => 'View Own Student Info', 'slug' => 'view_own_student_info', 'description' => 'View own scores and profile info'],
            ['name' => 'Manage Roles & Permissions', 'slug' => 'manage_roles_permissions', 'description' => 'Configure roles and their mapping permissions'],
        ];

        foreach ($permissions as $p) {
            Permission::create($p);
        }

        // 4. Assign Permissions to Roles
        $adminRole->permissions()->sync(Permission::pluck('id'));
        $teacherRole->permissions()->sync(Permission::whereIn('slug', [
            'view_classes',
            'view_subjects',
            'view_students',
            'create_students',
            'edit_students',
            'view_scores',
            'create_scores',
            'edit_scores'
        ])->pluck('id'));
        $studentRole->permissions()->sync(Permission::whereIn('slug', ['view_own_student_info'])->pluck('id'));

        // 5. Create Users (Admin & Teachers)
        $admin = User::create([
            'name' => 'System Administrator',
            'email' => 'admin@score.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'role_id' => $adminRole->id,
            'avatar' => null
        ]);

        $teacher1 = User::create([
            'name' => 'Professor John Miller',
            'email' => 'teacher@score.com',
            'password' => Hash::make('password'),
            'role' => 'teacher',
            'role_id' => $teacherRole->id,
            'avatar' => null
        ]);

        $teacher2 = User::create([
            'name' => 'Professor Sarah Connor',
            'email' => 'teacher2@score.com',
            'password' => Hash::make('password'),
            'role' => 'teacher',
            'role_id' => $teacherRole->id,
            'avatar' => null
        ]);

        // 6. Create Classes
        $classA = ClassModel::create([
            'name' => 'Class 2027A',
            'teacher_id' => $teacher1->id
        ]);

        $classB = ClassModel::create([
            'name' => 'Class 2027B',
            'teacher_id' => $teacher2->id
        ]);

        // 7. Create Subjects
        $math = Subject::create(['name' => 'Mathematics']);
        $english = Subject::create(['name' => 'English Language']);
        $programming = Subject::create(['name' => 'Computer Programming']);

        // 8. Assign Subjects to Classes with Teachers
        DB::table('class_subject')->insert([
            ['class_id' => $classA->id, 'subject_id' => $math->id, 'teacher_id' => $teacher1->id, 'created_at' => now(), 'updated_at' => now()],
            ['class_id' => $classA->id, 'subject_id' => $english->id, 'teacher_id' => $teacher1->id, 'created_at' => now(), 'updated_at' => now()],
            ['class_id' => $classA->id, 'subject_id' => $programming->id, 'teacher_id' => $teacher1->id, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('class_subject')->insert([
            ['class_id' => $classB->id, 'subject_id' => $math->id, 'teacher_id' => $teacher2->id, 'created_at' => now(), 'updated_at' => now()],
            ['class_id' => $classB->id, 'subject_id' => $programming->id, 'teacher_id' => $teacher2->id, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // 9. Create Students & Student User accounts
        $studentsA = [
            ['name' => 'Alice Johnson', 'gender' => 'Female'],
            ['name' => 'Bob Smith', 'gender' => 'Male'],
            ['name' => 'Charlie Brown', 'gender' => 'Male'],
            ['name' => 'Diana Prince', 'gender' => 'Female'],
            ['name' => 'Evan Wright', 'gender' => 'Male']
        ];

        foreach ($studentsA as $s) {
            $studentObj = Student::create([
                'class_id' => $classA->id,
                'gender' => $s['gender'],
            ]);

            // Create student login user account
            $cleanedEmail = strtolower(str_replace(' ', '', $s['name'])) . '@score.com';
            $userObj = User::create([
                'name' => $s['name'],
                'email' => $cleanedEmail,
                'password' => Hash::make('password'),
                'role' => 'student',
                'role_id' => $studentRole->id,
                'avatar' => null,
                'is_active' => true
            ]);
            $studentObj->update(['user_id' => $userObj->id]);
        }

        $studentsB = [
            ['name' => 'Fiona Gallagher', 'gender' => 'Female'],
            ['name' => 'George Costanza', 'gender' => 'Male'],
            ['name' => 'Hannah Baker', 'gender' => 'Female']
        ];

        foreach ($studentsB as $s) {
            $studentObj = Student::create([
                'class_id' => $classB->id,
                'gender' => $s['gender'],
            ]);

            // Create student login user account
            $cleanedEmail = strtolower(str_replace(' ', '', $s['name'])) . '@score.com';
            $userObj = User::create([
                'name' => $s['name'],
                'email' => $cleanedEmail,
                'password' => Hash::make('password'),
                'role' => 'student',
                'role_id' => $studentRole->id,
                'avatar' => null,
                'is_active' => true
            ]);
            $studentObj->update(['user_id' => $userObj->id]);
        }

        // 10. Create Grade Rules
        GradeRule::create(['min_score' => 85, 'max_score' => 100, 'grade' => 'A']);
        GradeRule::create(['min_score' => 70, 'max_score' => 84.99, 'grade' => 'B']);
        GradeRule::create(['min_score' => 55, 'max_score' => 69.99, 'grade' => 'C']);
        GradeRule::create(['min_score' => 50, 'max_score' => 54.99, 'grade' => 'D']);
        GradeRule::create(['min_score' => 0, 'max_score' => 49.99, 'grade' => 'F']);

        // 11. Create some initial Scores
        $alice = User::where('name', 'Alice Johnson')->first()->student;
        $bob   = User::where('name', 'Bob Smith')->first()->student;
        
        Score::create([
            'student_id' => $alice->id,
            'subject_id' => $math->id,
            'quiz' => 90.00,
            'assignment' => 85.00,
            'midterm' => 88.00,
            'final' => 92.00,
            'total' => 89.70,
            'grade' => 'A'
        ]);

        Score::create([
            'student_id' => $alice->id,
            'subject_id' => $programming->id,
            'quiz' => 95.00,
            'assignment' => 90.00,
            'midterm' => 92.00,
            'final' => 94.00,
            'total' => 93.20,
            'grade' => 'A'
        ]);

        Score::create([
            'student_id' => $bob->id,
            'subject_id' => $math->id,
            'quiz' => 60.00,
            'assignment' => 50.00,
            'midterm' => 55.00,
            'final' => 62.00,
            'total' => 58.30,
            'grade' => 'C'
        ]);

        Score::create([
            'student_id' => $bob->id,
            'subject_id' => $programming->id,
            'quiz' => 45.00,
            'assignment' => 40.00,
            'midterm' => 50.00,
            'final' => 48.00,
            'total' => 47.20,
            'grade' => 'F'
        ]);
    }
}
