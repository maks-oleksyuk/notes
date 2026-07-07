export * from './client';
// Raw requests + types re-exported directly (not `export *` from `./posts`/
// `./users`) — those barrels also carry `postsQueries`/`usersQueries`, which
// `blogQueries.posts`/`.users` (from `./queries`) already surfaces. Re-exporting
// them again here would bring back the "which differently-named object do I
// import" problem this structure exists to avoid. Reach into
// `clients/blog/posts`/`clients/blog/users` directly if you need them bare.
export {
  getPost,
  getPostComments,
  getPosts,
} from './posts';
export * from './queries';
export * from './urls';
export { getUser } from './users';

export type { Comment, Post } from './posts';
export type { User } from './users';
