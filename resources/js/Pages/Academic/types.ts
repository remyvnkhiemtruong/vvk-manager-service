import type { Paginated } from '../../types';

export type LookupItem = Record<string, string | number | null>;

export type AcademicLookups = {
    schoolYears: LookupItem[];
    semesters: LookupItem[];
    grades: LookupItem[];
    classes: LookupItem[];
    teachers: LookupItem[];
    subjects: LookupItem[];
    guardians: LookupItem[];
};

export type StudentRecord = {
    id: number;
    user_id: number | null;
    student_code: string;
    full_name: string;
    gender: string;
    birth_date: string | null;
    phone: string | null;
    email: string | null;
    address: string | null;
    status: string;
    current_class: null | {
        id: number;
        name: string;
        code: string | null;
        school_year: string | null;
        semester: string | null;
    };
    guardians: {
        id: number;
        full_name: string;
        phone: string | null;
        email: string | null;
        relationship: string | null;
        is_primary: boolean;
    }[];
};

export type TeacherRecord = {
    id: number;
    user_id: number | null;
    teacher_code: string;
    staff_code: string | null;
    full_name: string;
    gender: string | null;
    birth_date: string | null;
    position: string | null;
    department: string | null;
    specialization: string | null;
    qualification: string | null;
    hire_date: string | null;
    phone: string | null;
    email: string | null;
    status: string;
};

export type ClassRecord = {
    id: number;
    school_year_id: number;
    grade_id: number;
    homeroom_teacher_id: number | null;
    name: string;
    code: string | null;
    room: string | null;
    capacity: number | null;
    status: string;
    school_year: string | null;
    grade: string | null;
    homeroom_teacher: string | null;
    active_students_count: number;
};

export type CanCrud = {
    create: boolean;
    update: boolean;
    delete: boolean;
};

export type PaginatedStudents = Paginated<StudentRecord>;
export type PaginatedTeachers = Paginated<TeacherRecord>;
export type PaginatedClasses = Paginated<ClassRecord>;
