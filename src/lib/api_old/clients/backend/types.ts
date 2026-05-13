// ── Domain Types ────────────────────────────────────────────────
// Define your API response shapes here

export interface User {
  id: string;
  name: string;
  email: string;
  username?: string;
  bio?: string;
  avatarUrl?: string;
  createdAt: string;
  updatedAt: string;
}

export interface Post {
  id: string;
  authorId: string;
  author?: User;
  title: string;
  slug?: string;
  content: string;
  excerpt?: string;
  publishedAt?: string;
  createdAt: string;
  updatedAt: string;
}

export interface Comment {
  id: string;
  postId: string;
  authorId: string;
  author?: User;
  content: string;
  createdAt: string;
}

// ── Request/Response Wrappers ─────────────────────────────────────

export interface PaginatedResponse<T> {
  data: T[];
  total: number;
  page: number;
  limit: number;
}

export interface ApiError {
  message: string;
  code?: string;
  field?: string;
}

// ── Request DTOs ─────────────────────────────────────────────────

export interface UpdateUserDto {
  name?: string;
  bio?: string;
  avatarUrl?: string;
}

export interface CreatePostDto {
  title: string;
  content: string;
  excerpt?: string;
}

export interface UpdatePostDto {
  title?: string;
  content?: string;
  excerpt?: string;
  published?: boolean;
}
