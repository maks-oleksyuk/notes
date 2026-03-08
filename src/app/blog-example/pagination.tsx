import Link from 'next/link';

interface PaginationProps {
  currentPage: number;
  totalPages: number;
}

/**
 * Server Component pagination using Link.
 * No client JS needed — navigation is just <a> tags with ?page=N.
 */
export function Pagination({ currentPage, totalPages }: PaginationProps) {
  const pages = getPageNumbers(currentPage, totalPages);

  return (
    <nav className='flex items-center justify-center gap-1'>
      <PaginationLink page={currentPage - 1} disabled={currentPage <= 1}>
        Previous
      </PaginationLink>

      {pages.map((page, _i, arr) =>
        page === null ? (
          <span
            key={`ellipsis-after-${arr[_i - 1]}`}
            className='px-2 text-gray-400'
          >
            ...
          </span>
        ) : (
          <PaginationLink key={page} page={page} active={page === currentPage}>
            {page}
          </PaginationLink>
        ),
      )}

      <PaginationLink
        page={currentPage + 1}
        disabled={currentPage >= totalPages}
      >
        Next
      </PaginationLink>
    </nav>
  );
}

function PaginationLink({
  page,
  disabled,
  active,
  children,
}: {
  page: number;
  disabled?: boolean;
  active?: boolean;
  children: React.ReactNode;
}) {
  const base = 'px-3 py-1 rounded text-sm';

  if (disabled) {
    return (
      <span className={`${base} text-gray-300 cursor-not-allowed`}>
        {children}
      </span>
    );
  }

  if (active) {
    return <span className={`${base} bg-blue-600 text-white`}>{children}</span>;
  }

  return (
    <Link
      href={`/blog-example?page=${page}`}
      className={`${base} border hover:bg-gray-50`}
    >
      {children}
    </Link>
  );
}

/** Generate page numbers with ellipsis: [1, 2, ..., 5, 6, 7, ..., 19, 20] */
function getPageNumbers(current: number, total: number): (number | null)[] {
  if (total <= 7) {
    return Array.from({ length: total }, (_, i) => i + 1);
  }

  const pages: (number | null)[] = [1];

  if (current > 3) pages.push(null);

  const start = Math.max(2, current - 1);
  const end = Math.min(total - 1, current + 1);

  for (let i = start; i <= end; i++) {
    pages.push(i);
  }

  if (current < total - 2) pages.push(null);

  pages.push(total);

  return pages;
}
