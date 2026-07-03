import { dehydrate, HydrationBoundary } from '@tanstack/react-query';
import { redirect } from 'next/navigation';

import { blogQueries } from '@/lib/api/clients/blog';
import { makeQueryClient } from '@/lib/api/query-client';

import { Pagination } from './pagination';
import { PostsList } from './posts-list';

import type { BlogPageData } from '@/lib/api/clients/blog';

interface PageProps {
  searchParams: Promise<{ page?: string }>;
}

export default async function BlogExamplePage({ searchParams }: PageProps) {
  const params = await searchParams;
  const currentPage = Math.max(1, Number(params.page) || 1);

  // A fresh QueryClient per request (see query-client.ts) — `fetchQuery`
  // populates it *and* returns the data directly, unlike `prefetchQuery`
  // (which returns void), so this page.tsx can read `totalPages` for
  // `<Pagination>` without a second fetch.
  const queryClient = makeQueryClient();

  let data: BlogPageData;
  try {
    data = await queryClient.fetchQuery(blogQueries.pageData(currentPage));
  } catch {
    return (
      <main className='max-w-2xl mx-auto p-8'>
        <h1 className='text-3xl font-bold mb-4'>Blog API Example</h1>
        <p className='text-red-600'>
          Posts are temporarily unavailable. Please try again later.
        </p>
      </main>
    );
  }

  if (data.posts.length === 0 && currentPage > 1) {
    const preserved = new URLSearchParams(params as Record<string, string>);
    preserved.delete('page');
    const qs = preserved.toString();
    redirect(`/blog-example${qs ? `?${qs}` : ''}`);
  }

  return (
    <main className='max-w-2xl mx-auto p-8 space-y-6'>
      <h1 className='text-3xl font-bold'>Blog API Example</h1>
      <p className='text-sm text-gray-500'>
        Server Component prefetch + client-side TanStack Query — fetched from
        JSONPlaceholder with pagination (page {currentPage} of {data.totalPages}
        )
      </p>

      <HydrationBoundary state={dehydrate(queryClient)}>
        <PostsList page={currentPage} />
      </HydrationBoundary>

      <Pagination currentPage={currentPage} totalPages={data.totalPages} />
    </main>
  );
}
