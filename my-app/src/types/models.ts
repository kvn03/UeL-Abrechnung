export interface Timesheet {
    date: string
    hours: number
    note?: string
    trainerId?: string
}

export interface Rolle {
    id: number;
    name: string; // z.B. 'Administrator', 'Abteilungsleiter', 'Übungsleiter'
    // Fügen Sie hier weitere Felder hinzu, die Ihre 'rolle_definitions' Tabelle hat
}

export interface Abteilung {
    id: number;
    name: string;
}

export interface User {
    id: number;
    name: string;
    email: string;
    rolle_id?: number;
    abteilung_id?: number;
    // Relationen (werden vom Backend via ->load() mitgeschickt)
    rolle?: Rolle;
    abteilung?: Abteilung;
    created_at: string;
    updated_at: string;
}

export interface LoginCredentials {
    email: string;
    password: string;
}