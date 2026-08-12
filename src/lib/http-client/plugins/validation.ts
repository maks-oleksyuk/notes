import { ValidationError } from '@/lib/http-client/core';

import type { ZodError } from 'zod';
import type { ApiPlugin } from '@/lib/http-client/core';

type ZodIssue = ZodError['issues'][number];
// A union issue nests the per-arm failures under `.errors` — not on the base
// `ZodIssue` type, so widen locally to reach them.
type UnionIssue = ZodIssue & { errors?: ZodIssue[][] };

const MAX_ISSUES = 5;

// Union failures wrap the real problems (one set per arm) under `.errors`;
// flatten them so the summary shows the actual field mismatches, not a useless
// top-level "Invalid input".
function flattenIssues(issues: readonly ZodIssue[]): ZodIssue[] {
  const out: ZodIssue[] = [];
  for (const issue of issues) {
    const nested = (issue as UnionIssue).errors;
    if (Array.isArray(nested)) out.push(...flattenIssues(nested.flat()));
    else out.push(issue);
  }
  return out;
}

function formatIssue(issue: ZodIssue): string {
  const path =
    issue.path.length > 0
      ? issue.path.map((seg) => String(seg)).join('.')
      : '(root)';
  // Zod prefixes every type error with "Invalid input: " — drop it, the path
  // already says which input.
  const reason = issue.message.replace(/^Invalid input:\s*/iu, '');
  return `${path}: ${reason}`;
}

// One bullet per field instead of one long semicolon-joined blob, e.g.:
//   Response validation failed (2 issues):
//     • data.0.address: expected string, received object
//     • data.1.address: expected string, received object
// The root of the response body shows as `(root)`.
export function summarizeIssues(issues: readonly ZodIssue[]): string {
  // Union arms produce duplicate lines (same field fails in each arm) — dedupe.
  const seen = new Set<string>();
  const lines: string[] = [];
  for (const issue of flattenIssues(issues)) {
    const line = formatIssue(issue);
    if (seen.has(line)) continue;
    seen.add(line);
    lines.push(line);
  }

  const shown = lines.slice(0, MAX_ISSUES);
  const extra = lines.length - shown.length;
  const count = `${lines.length} issue${lines.length === 1 ? '' : 's'}`;
  const bullets = shown.map((l) => `  • ${l}`).join('\n');
  return extra > 0
    ? `${count}:\n${bullets}\n  • …and ${extra} more`
    : `${count}:\n${bullets}`;
}

export function validation(): ApiPlugin {
  return {
    name: 'validation',
    onResponse(response, options) {
      const schema = options.schema;
      if (schema) {
        const parsed = schema.safeParse(response.data);
        if (!parsed.success) {
          throw new ValidationError(
            `Response validation failed — ${summarizeIssues(parsed.error.issues)}`,
            {
              url: response.url,
              errors: parsed.error.issues,
              data: response.data,
            },
          );
        }
        response.data = parsed.data;
      }
    },
  };
}
