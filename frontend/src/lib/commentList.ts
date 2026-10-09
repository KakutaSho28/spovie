import type { Comment } from '../types';

/**
 * コメント一覧に追加する。同じ id が既にあれば置き換える。
 * 自分の投稿（API 応答で追加済み）と WebSocket 通知の両方が届いても二重にならない。
 */
export function upsertComment(list: Comment[], comment: Comment): Comment[] {
  const exists = list.some((c) => c.id === comment.id);
  return exists ? list.map((c) => (c.id === comment.id ? comment : c)) : [...list, comment];
}

export function removeComment(list: Comment[], id: number): Comment[] {
  return list.filter((c) => c.id !== id);
}

/** 通知の is_own は閲覧者に依存しないので、自分のユーザー ID から決め直す */
export function withOwnership(comment: Comment, myUserId: number | undefined): Comment {
  return { ...comment, is_own: comment.user.id === myUserId };
}
