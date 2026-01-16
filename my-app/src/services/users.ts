import apiClient from './apiClient';

export interface DepartmentDto {
    id: number;
    name: string;
}

export interface UserDto {
    id: number;
    name: string;
    vorname: string;
    email: string;
    isAdmin: boolean;
    isGeschaeftsstelle: boolean;
    departmentHeadDepartments: DepartmentDto[];
    trainerDepartments: DepartmentDto[];
}

export interface UpdateUserRolesPayload {
    isAdmin: boolean;
    isGeschaeftsstelle: boolean;
    roles: {
        departmentHead: number[];
        trainer: number[];
    };
}

export async function fetchUsers(): Promise<UserDto[]> {
    // 1. URL Prüfen: Heißt die Route wirklich '/admin/users' oder eher '/api/admin/users'?
    // Falls dein apiClient keine baseURL mit '/api' hat, musst du das hier ggf. anpassen.
    const response = await apiClient.get<any>('/admin/users');

    // 2. Robustes Auslesen der Daten:

    // Fall A: API liefert { data: [...] } (Standard bei Laravel API Resources)
    if (response.data?.data && Array.isArray(response.data.data)) {
        return response.data.data;
    }

    // Fall B: API liefert direkt das Array [...]
    if (Array.isArray(response.data)) {
        return response.data;
    }

    // Fall C: API liefert dein erwartetes { users: [...] }
    if (response.data?.users && Array.isArray(response.data.users)) {
        return response.data.users;
    }

    // Fallback: Leeres Array zurückgeben, damit das Frontend nicht abstürzt
    console.warn('Unerwartetes Antwortformat von /admin/users:', response.data);
    return [];
}

export async function updateUserRoles(userId: number, payload: UpdateUserRolesPayload): Promise<void> {
    await apiClient.put(`/admin/users/${userId}/roles`, payload);
}