<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TeacherDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $teacherPassword = Hash::make('teacher123');
        $studentPassword = Hash::make('student123');
        $adminPassword = Hash::make('admin123');

        $users = [
            // ── Admin Accounts ──
            [
                'idnumber' => '23-0001',
                'name' => 'System Admin',
                'email' => 'admin@reviso.com',
                'program' => null,
                'role' => 'admin',
                'status' => 'approved',
                'password' => $adminPassword,
                'email_verified_at' => now(),
            ],
            [
                'idnumber' => 'ADMIN-001',
                'name' => 'Administrator One',
                'email' => 'admin1@reviso.com',
                'program' => null,
                'role' => 'admin',
                'status' => 'approved',
                'password' => $adminPassword,
                'email_verified_at' => now(),
            ],

            // ── Student Accounts (Accountancy, Psychology, Education) ──
            [
                'idnumber' => '23-0101',
                'name' => 'Juan Dela Cruz',
                'email' => 'student.acc@reviso.com',
                'program' => 'accountancy',
                'role' => 'student',
                'status' => 'approved',
                'password' => $studentPassword,
                'email_verified_at' => now(),
            ],
            [
                'idnumber' => '23-0102',
                'name' => 'Maria Santos',
                'email' => 'student.psych@reviso.com',
                'program' => 'psych',
                'role' => 'student',
                'status' => 'approved',
                'password' => $studentPassword,
                'email_verified_at' => now(),
            ],
            [
                'idnumber' => '23-0103',
                'name' => 'Mark Reyes',
                'email' => 'student.educ@reviso.com',
                'program' => 'educ',
                'role' => 'student',
                'status' => 'approved',
                'password' => $studentPassword,
                'email_verified_at' => now(),
            ],

            // ── Teacher Accounts ──
            [
                'idnumber' => '23-0801',
                'name' => 'Prof. Elena Ramos',
                'email' => 'teacher.psych@reviso.com',
                'program' => 'psych',
                'role' => 'teacher',
                'status' => 'approved',
                'password' => $teacherPassword,
                'email_verified_at' => now(),
            ],
            [
                'idnumber' => '23-0802',
                'name' => 'Prof. Carlos Santos',
                'email' => 'teacher.educ@reviso.com',
                'program' => 'educ',
                'role' => 'teacher',
                'status' => 'approved',
                'password' => $teacherPassword,
                'email_verified_at' => now(),
            ],
            [
                'idnumber' => '23-0803',
                'name' => 'Prof. Teresa Diaz',
                'email' => 'teacher.acc@reviso.com',
                'program' => 'accountancy',
                'role' => 'teacher',
                'status' => 'approved',
                'password' => $teacherPassword,
                'email_verified_at' => now(),
            ],
        ];

        foreach ($users as $userData) {
            User::updateOrCreate(
                ['idnumber' => $userData['idnumber']],
                [
                    'name' => $userData['name'],
                    'email' => $userData['email'],
                    'password' => $userData['password'],
                    'role' => $userData['role'],
                    'program' => $userData['program'],
                    'status' => $userData['status'],
                    'email_verified_at' => $userData['email_verified_at'],
                ]
            );
        }
    }
}
