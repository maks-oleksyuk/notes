import { redirect } from 'next/navigation';

import { getPosts, getUser } from '@/lib/api/clients/blog';

import { Pagination } from './pagination';

import type { Post } from '@/lib/api/clients/blog';

const POSTS_PER_PAGE = 5;

interface PageProps {
  searchParams: Promise<{ page?: string }>;
}

export default async function BlogExamplePage({ searchParams }: PageProps) {
  const params = await searchParams;
  const currentPage = Math.max(1, Number(params.page) || 1);

  const result = await fetchPageData(currentPage);

  if (!result) {
    return (
      <main className='max-w-2xl mx-auto p-8'>
        <h1 className='text-3xl font-bold mb-4'>Blog API Example</h1>
        <p className='text-red-600'>
          Posts are temporarily unavailable. Please try again later.
        </p>
      </main>
    );
  }

  if (result.posts.length === 0 && currentPage > 1) {
    const preserved = new URLSearchParams(params as Record<string, string>);
    preserved.delete('page');
    const qs = preserved.toString();
    redirect(`/blog-example${qs ? `?${qs}` : ''}`);
  }

  return (
    <main className='max-w-2xl mx-auto p-8 space-y-6'>
      <h1 className='text-3xl font-bold'>Blog API Example</h1>
      <p className='text-sm text-gray-500'>
        Server Component — fetched from JSONPlaceholder with pagination (page{' '}
        {currentPage} of {result.totalPages})
      </p>

      <ul className='space-y-4'>
        {result.posts.map((post) => (
          <li key={post.id} className='border rounded-lg p-4 space-y-1'>
            <h2 className='font-semibold'>{post.title}</h2>
            <p className='text-sm text-gray-500'>
              by {result.users.get(post.userId) ?? `User #${post.userId}`}
            </p>
            <p className='text-gray-700 text-sm line-clamp-2'>{post.body}</p>
          </li>
        ))}
      </ul>

      <Pagination currentPage={currentPage} totalPages={result.totalPages} />
    </main>
  );
}

interface PageData {
  posts: Post[];
  users: Map<number, string>;
  totalPages: number;
}

async function fetchPageData(page: number): Promise<PageData | null> {
  try {
    const response = await getPosts(page, POSTS_PER_PAGE);

    // JSONPlaceholder returns X-Total-Count header.
    // Real APIs might return total in response body instead.
    const totalCount = Number(response.headers.get('X-Total-Count')) || 0;
    const totalPages = Math.ceil(totalCount / POSTS_PER_PAGE);

    const userIds = [...new Set(response.data.map((p) => p.userId))];
    const userResults = await Promise.all(userIds.map((id) => getUser(id)));

    const users = new Map<number, string>();
    for (const result of userResults) {
      users.set(result.data.id, result.data.name);
    }

    return { posts: response.data, users, totalPages };
  } catch {
    return null;
  }
}
