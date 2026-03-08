// ── Posts API ─────────────────────────────────────────────────────

import { backendClient } from './client';
import type {
  CreatePostDto,
  PaginatedResponse,
  Post,
  UpdatePostDto,
} from './types';

// ── Queries ───────────────────────────────────────────────────────

export async function getPosts(params?: {
  page?: number;
  limit?: number;
  authorId?: string;
}): Promise<PaginatedResponse<Post>> {
  const response = await backendClient.get<PaginatedResponse<Post>>('/posts', {
    params: params as Record<string, string | number | boolean>,
  });
  return response.data;
}

export async function getPost(idOrSlug: string): Promise<Post> {
  const response = await backendClient.get<{ post: Post }>(
    `/posts/${idOrSlug}`,
  );
  return response.data.post;
}

export async function getPostComments(postId: string) {
  const response = await backendClient.get<{
    comments: Array<{ id: string; content: string; author: { name: string } }>;
  }>(`/posts/${postId}/comments`);
  return response.data.comments;
}

// ── Mutations ─────────────────────────────────────────────────────

export async function createPost(data: CreatePostDto): Promise<Post> {
  const response = await backendClient.post<{ post: Post }>('/posts', data);
  return response.data.post;
}

export async function updatePost(
  id: string,
  data: UpdatePostDto,
): Promise<Post> {
  const response = await backendClient.patch<{ post: Post }>(
    `/posts/${id}`,
    data,
  );
  return response.data.post;
}

export async function deletePost(id: string): Promise<void> {
  await backendClient.delete(`/posts/${id}`);
}

export async function publishPost(id: string): Promise<Post> {
  const response = await backendClient.post<{ post: Post }>(
    `/posts/${id}/publish`,
  );
  return response.data.post;
}

export async function unpublishPost(id: string): Promise<Post> {
  const response = await backendClient.delete<{ post: Post }>(
    `/posts/${id}/publish`,
  );
  return response.data.post;
}
