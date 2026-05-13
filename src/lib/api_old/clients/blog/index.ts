import type { ApiResponse } from '@/lib/api';
import { ApiClient } from '@/lib/api';
import { BLOG_URLS } from './urls';

// ── Response types ─────────────────────────────────────────

export interface Post {
  userId: number;
  id: number;
  title: string;
  body: string;
}

export interface Comment {
  postId: number;
  id: number;
  name: string;
  email: string;
  body: string;
}

export interface User {
  id: number;
  name: string;
  username: string;
  email: string;
  phone: string;
  website: string;
  company: { name: string; catchPhrase: string; bs: string };
  address: {
    street: string;
    suite: string;
    city: string;
    zipcode: string;
  };
}

// ── Client ─────────────────────────────────────────────────

const isDebug = process.env.NEXT_PUBLIC_DEBUG_MODE === 'true';

const blogClient = new ApiClient({
  baseUrl: 'https://jsonplaceholder.typicode.com',
  timeout: 10_000,
  cache: { revalidate: 60, tags: ['blog'] },
  retry: { maxAttempts: 2, initialDelay: 500, maxDelay: 3_000 },
  logger: { level: isDebug ? 'debug' : 'error' },
});

// ── API functions ──────────────────────────────────────────

export async function getPosts(
  page = 1,
  limit = 10,
): Promise<ApiResponse<Post[]>> {
  return blogClient.get<Post[]>(BLOG_URLS.posts, {
    params: { _page: page, _limit: limit },
  });
}

export async function getPost(id: number): Promise<ApiResponse<Post>> {
  return blogClient.get<Post>(BLOG_URLS.post(id));
}

export async function getPostComments(
  postId: number,
): Promise<ApiResponse<Comment[]>> {
  return blogClient.get<Comment[]>(BLOG_URLS.comments(postId));
}

export async function getUser(id: number): Promise<ApiResponse<User>> {
  return blogClient.get<User>(BLOG_URLS.user(id));
}
