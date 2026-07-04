<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\ClassModel;
use App\Models\Subject;
use App\Models\Student;
use App\Models\Score;
use App\Models\GradeRule;
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

        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON');
        } else {
            DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        }

        // 2. Create Users (Admin & Teachers)
        $admin = User::create([
            'name' => 'System Administrator',
            'email' => 'admin@score.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'avatar' => null
        ]);

        $teacher1 = User::create([
            'name' => 'Professor John Miller',
            'email' => 'teacher@score.com',
            'password' => Hash::make('password'),
            'role' => 'teacher',
            'avatar' => null
        ]);

        $teacher2 = User::create([
            'name' => 'Professor Sarah Connor',
            'email' => 'teacher2@score.com',
            'password' => Hash::make('password'),
            'role' => 'teacher',
            'avatar' => null
        ]);

        // 3. Create Classes
        $classA = ClassModel::create([
            'name' => 'Class 2027A',
            'teacher_id' => $teacher1->id
        ]);

        $classB = ClassModel::create([
            'name' => 'Class 2027B',
            'teacher_id' => $teacher2->id
        ]);

        // 4. Create Subjects
        $math = Subject::create(['name' => 'Mathematics']);
        $english = Subject::create(['name' => 'English Language']);
        $programming = Subject::create(['name' => 'Computer Programming']);

        // 5. Assign Subjects to Classes with Teachers
        // Class A gets all three subjects taught by Professor Miller (teacher1)
        DB::table('class_subject')->insert([
            ['class_id' => $classA->id, 'subject_id' => $math->id, 'teacher_id' => $teacher1->id, 'created_at' => now(), 'updated_at' => now()],
            ['class_id' => $classA->id, 'subject_id' => $english->id, 'teacher_id' => $teacher1->id, 'created_at' => now(), 'updated_at' => now()],
            ['class_id' => $classA->id, 'subject_id' => $programming->id, 'teacher_id' => $teacher1->id, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Class B gets Math and Programming taught by Professor Connor (teacher2)
        DB::table('class_subject')->insert([
            ['class_id' => $classB->id, 'subject_id' => $math->id, 'teacher_id' => $teacher2->id, 'created_at' => now(), 'updated_at' => now()],
            ['class_id' => $classB->id, 'subject_id' => $programming->id, 'teacher_id' => $teacher2->id, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // 6. Create Students
        $studentsA = [
            ['name' => 'Alice Johnson', 'gender' => 'Female'],
            ['name' => 'Bob Smith', 'gender' => 'Male'],
            ['name' => 'Charlie Brown', 'gender' => 'Male'],
            ['name' => 'Diana Prince', 'gender' => 'Female'],
            ['name' => 'Evan Wright', 'gender' => 'Male']
        ];

        foreach ($studentsA as $s) {
            Student::create([
                'class_id' => $classA->id,
                'name' => $s['name'],
                'gender' => $s['gender'],
                'photo' => null
            ]);
        }

        $studentsB = [
            ['name' => 'Fiona Gallagher', 'gender' => 'Female'],
            ['name' => 'George Costanza', 'gender' => 'Male'],
            ['name' => 'Hannah Baker', 'gender' => 'Female']
        ];

        foreach ($studentsB as $s) {
            Student::create([
                'class_id' => $classB->id,
                'name' => $s['name'],
                'gender' => $s['gender'],
                'photo' => null
            ]);
        }

        // 7. Create Grade Rules
        GradeRule::create(['min_score' => 85, 'max_score' => 100, 'grade' => 'A']);
        GradeRule::create(['min_score' => 70, 'max_score' => 84.99, 'grade' => 'B']);
        GradeRule::create(['min_score' => 55, 'max_score' => 69.99, 'grade' => 'C']);
        GradeRule::create(['min_score' => 50, 'max_score' => 54.99, 'grade' => 'D']);
        GradeRule::create(['min_score' => 0, 'max_score' => 49.99, 'grade' => 'F']);

        // 8. Create some initial Scores
        // Let's score Alice, Bob, and Charlie for Class A
        $alice = Student::where('name', 'Alice Johnson')->first();
        $bob = Student::where('name', 'Bob Smith')->first();
        
        // Formula: Total = (Quiz * 20%) + (Assignment * 10%) + (Midterm * 30%) + (Final * 40%)
        // Alice Math: 90, 85, 88, 92 -> Total: 18 + 8.5 + 26.4 + 36.8 = 89.7 -> A
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

        // Alice Programming: 95, 90, 92, 94 -> Total: 19 + 9 + 27.6 + 37.6 = 93.2 -> A
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

        // Bob Math: 60, 50, 55, 62 -> Total: 12 + 5 + 16.5 + 24.8 = 58.3 -> C
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

        // Bob Programming: 45, 40, 50, 48 -> Total: 9 + 4 + 15 + 19.2 = 47.2 -> F
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
