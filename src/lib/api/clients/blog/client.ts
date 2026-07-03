// Same reasoning as clients/backend/client.ts — import from `@/lib/api/core`
// directly, never the root barrel (real circular import, review.md A4).
import { HttpClient } from '@/lib/api/core';
import { logger } from '@/lib/api/plugins';

/**
 * Client for the JSONPlaceholder Blog API.
 */
export const blogApi = new HttpClient('https://jsonplaceholder.typicode.com', {
  next: {
    revalidate: 60,
    tags: ['blog'],
  },
  plugins: [logger({ level: 'info', prefix: 'blog' })],
});

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

export async function getPosts(page = 1, limit = 10) {
  return blogApi.get<Post[]>('/posts', {
    params: { _page: page, _limit: limit },
  });
}

export async function getPost(id: number) {
  return blogApi.get<Post>(`/posts/${id}`);
}

export async function getPostComments(postId: number) {
  return blogApi.get<Comment[]>(`/posts/${postId}/comments`);
}

export async function getUser(id: number) {
  return blogApi.get<User>(`/users/${id}`, {
    next: { revalidate: 3600 },
  });
}
