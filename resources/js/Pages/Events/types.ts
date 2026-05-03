import type { Paginated } from '../../types';

export type Lookup = { id: number; name?: string; full_name?: string; student_code?: string; teacher_code?: string; school_year_id?: number };

export type EventItem = {
    id: number;
    school_year_id: number;
    school_year_name?: string | null;
    semester_id?: number | null;
    semester_name?: string | null;
    title: string;
    event_type: string;
    type_label: string;
    organizer_unit?: string | null;
    location?: string | null;
    target_audience: string;
    registration_modes: string[];
    starts_at?: string | null;
    ends_at?: string | null;
    description?: string | null;
    summary_report?: string | null;
    conduct_points_per_student: number;
    class_competition_points: number | string;
    status: string;
    status_label: string;
    categories_count?: number | null;
    registrations_count?: number | null;
    teams_count?: number | null;
    matches_count?: number | null;
    results_count?: number | null;
    awards_count?: number | null;
    summarized_at?: string | null;
};

export type EventLookups = {
    schoolYears: Lookup[];
    semesters: Lookup[];
    classes: Lookup[];
    students: Lookup[];
    teachers: Lookup[];
    types: Record<string, string>;
    statuses: Record<string, string>;
    targetAudiences: Record<string, string>;
    registrationModes: Record<string, string>;
    registrationStatuses: Record<string, string>;
    participationTypes: Record<string, string>;
    scoringModes: Record<string, string>;
    sportRules: Record<string, string>;
    organizerRoles: Record<string, string>;
    awardTypes: Record<string, string>;
};

export type EventCategory = {
    id: number;
    event_id: number;
    name: string;
    category_type?: string | null;
    participation_type: string;
    max_participants?: number | null;
    gender_rule?: string | null;
    allowed_grade_ids?: number[];
    allowed_class_ids?: number[];
    rules_text?: string | null;
    scoring_mode: string;
    sport_rule?: string | null;
    judge_score_mode: string;
    drop_extreme_scores: boolean;
    max_score: number | string;
    order_index: number;
    status: string;
    criteria: EventCriterion[];
};

export type EventCriterion = {
    id?: number;
    event_category_id?: number;
    code?: string | null;
    name: string;
    description?: string | null;
    max_score: number | string;
    weight: number | string;
    order_index: number | string;
    status: string;
};

export type EventRegistration = {
    id: number;
    event_id: number;
    event_title?: string | null;
    event_category_id?: number | null;
    category_name?: string | null;
    registration_type: string;
    registration_type_label: string;
    event_team_id?: number | null;
    team_name?: string | null;
    class_id?: number | null;
    class_name?: string | null;
    student_id?: number | null;
    student_code?: string | null;
    student_name?: string | null;
    participant_name: string;
    members: { id: number; student_code?: string; full_name?: string; role?: string | null }[];
    status: string;
    status_label: string;
    approved_by?: string | null;
    rejection_reason?: string | null;
};

export type EventTeam = {
    id: number;
    event_category_id?: number | null;
    category_name?: string | null;
    name: string;
    class_name?: string | null;
    captain_name?: string | null;
    group_code?: string | null;
    seed_number?: number | null;
    status: string;
    members: { id: number; student_code?: string; full_name?: string; role?: string | null }[];
};

export type EventMatch = {
    id: number;
    event_category_id?: number | null;
    category_name?: string | null;
    sport_rule?: string | null;
    starts_at?: string | null;
    location?: string | null;
    home_team_name?: string | null;
    away_team_name?: string | null;
    group_code?: string | null;
    round?: string | null;
    home_score?: number | string | null;
    away_score?: number | string | null;
    home_sets_won?: number;
    away_sets_won?: number;
    winner_name?: string | null;
    status: string;
    sets: { set_number: number; home_score: number | string; away_score: number | string }[];
};

export type EventStanding = {
    id: number;
    event_category_id: number;
    team_name?: string | null;
    group_code?: string | null;
    played: number;
    won: number;
    drawn: number;
    lost: number;
    points: number | string;
    score_for: number | string;
    score_against: number | string;
    score_diff: number | string;
    set_diff: number | string;
    buchholz: number | string;
    rank?: number | null;
    needs_manual_rank: boolean;
};

export type EventResult = {
    id: number;
    event_category_id?: number | null;
    category_name?: string | null;
    event_registration_id?: number | null;
    event_team_id?: number | null;
    team_name?: string | null;
    participant_name?: string | null;
    class_name?: string | null;
    score?: number | string | null;
    rank?: number | null;
    award_title?: string | null;
    conduct_points?: number | null;
    class_points?: number | string | null;
    remarks?: string | null;
    status: string;
    judge_scores?: { event_category_criterion_id?: number; criterion_name?: string; score: number | string; comment?: string | null; judge_name?: string | null }[];
};

export type EventAward = {
    id: number;
    event_result_id?: number;
    category_name?: string | null;
    participant_name?: string | null;
    award_type: string;
    rank?: number | null;
    title: string;
    description?: string | null;
    awarded_date?: string | null;
    recipient_count?: number;
};

export type EventPage<T = unknown> = T & {
    lookups?: EventLookups;
    event?: EventItem | null;
    events?: Paginated<EventItem>;
    categories?: EventCategory[];
    registrations?: Paginated<EventRegistration> | EventRegistration[];
    teams?: EventTeam[];
    matches?: EventMatch[];
    standings?: EventStanding[];
    results?: EventResult[];
    awards?: EventAward[];
    filters?: Record<string, string>;
};
