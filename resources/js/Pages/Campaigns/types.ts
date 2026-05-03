import type { Paginated } from '../../types';

export type Lookup = { id: number; name: string; school_year_id?: number; full_name?: string; student_code?: string };

export type Campaign = {
    id: number;
    school_year_id: number;
    school_year_name?: string;
    semester_id?: number | null;
    semester_name?: string | null;
    title: string;
    campaign_type: string;
    type_label: string;
    organizer_unit?: string | null;
    target_audience: string;
    registration_modes: string[];
    start_date?: string | null;
    end_date?: string | null;
    description?: string | null;
    summary_report?: string | null;
    conduct_points_per_student: number;
    class_competition_points: string | number;
    status: string;
    status_label: string;
    participants_count?: number | null;
    results_count?: number | null;
    summarized_at?: string | null;
};

export type CampaignLookups = {
    schoolYears: Lookup[];
    semesters: Lookup[];
    classes: Lookup[];
    students: Lookup[];
    types: Record<string, string>;
    statuses: Record<string, string>;
    targetAudiences: Record<string, string>;
    registrationModes: Record<string, string>;
};

export type Registration = {
    id: number;
    campaign_id: number;
    campaign_title?: string;
    participant_type: string;
    participant_type_label: string;
    class_id?: number | null;
    class_name?: string | null;
    student_id?: number | null;
    student_code?: string | null;
    student_name?: string | null;
    participant_name: string;
    members: { id: number; student_code?: string; full_name?: string; role?: string | null }[];
    status: string;
    status_label: string;
    registered_by?: string | null;
    approved_by?: string | null;
    rejection_reason?: string | null;
    note?: string | null;
};

export type Criterion = {
    id?: number;
    campaign_id?: number;
    code?: string | null;
    name: string;
    description?: string | null;
    max_score: number | string;
    weight: number | string;
    order_index: number | string;
    status: string;
};

export type ResultRow = {
    id: number;
    campaign_participant_id: number;
    participant_type?: string;
    participant_name?: string;
    class_name?: string | null;
    student_code?: string | null;
    student_name?: string | null;
    member_count: number;
    total_score?: string | number | null;
    rank?: number | null;
    award_title?: string | null;
    conduct_points?: number | null;
    class_points?: string | number | null;
    status: string;
    note?: string | null;
    scores?: { campaign_criterion_id: number; criterion_name?: string; score: string | number; note?: string | null }[];
    files?: { id: number; original_name?: string | null; size: number }[];
};

export type CampaignPage<T = unknown> = T & {
    lookups?: CampaignLookups;
    campaign?: Campaign | null;
    campaigns?: Paginated<Campaign>;
    registrations?: Paginated<Registration> | Registration[];
    criteria?: Criterion[];
    results?: ResultRow[];
    ranking?: { rows: ResultRow[]; campaign: { id: number; title: string; status: string } };
    filters?: Record<string, string>;
};
