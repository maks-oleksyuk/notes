// ── Users API ─────────────────────────────────────────────────────

import { backendClient } from './client';
import type { PaginatedResponse, UpdateUserDto, User } from './types';

// ── Queries ───────────────────────────────────────────────────────

export async function getUsers(params?: {
  page?: number;
  limit?: number;
  search?: string;
}): Promise<PaginatedResponse<User>> {
  const response = await backendClient.get<PaginatedResponse<User>>('/users', {
    params: params as Record<string, string | number | boolean>,
  });
  return response.data;
}

export async function getUser(id: string): Promise<User> {
  const response = await backendClient.get<{ user: User }>(`/users/${id}`);
  return response.data.user;
}

export async function getCurrentUser(): Promise<User> {
  const response = await backendClient.get<{ user: User }>('/users/me');
  return response.data.user;
}

// ── Mutations ─────────────────────────────────────────────────────

export async function updateUser(
  id: string,
  data: UpdateUserDto,
): Promise<User> {
  const response = await backendClient.patch<{ user: User }>(
    `/users/${id}`,
    data,
  );
  return response.data.user;
}

export async function updateProfile(data: UpdateUserDto): Promise<User> {
  const response = await backendClient.patch<{ user: User }>('/users/me', data);
  return response.data.user;
}

export async function uploadAvatar(file: File): Promise<{ avatarUrl: string }> {
  const formData = new FormData();
  formData.append('avatar', file);

  const response = await backendClient.post<{ avatarUrl: string }>(
    '/users/me/avatar',
    formData,
  );
  return response.data;
}

// ── Example: Usage in Server Component ───────────────────────────

// export default async function ProfilePage() {
//   const user = await getCurrentUser();
//
//   return (
//     <main>
//       <h1>{user.name}</h1>
//       <p>{user.bio}</p>
//     </main>
//   );
// }
