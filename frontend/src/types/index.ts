/** 動画種別 */
export type VideoType = 'youtube' | 'upload';

/** 動画 */
export type Video = {
  id: number;
  type: VideoType;
  youtube_video_id: string | null;
  file_url: string | null;
  title: string;
  team: Pick<Team, 'id' | 'name'> | null;
  created_at: string;
};

/** 描画オブジェクト1つ分（相対座標 0〜1 で保存） */
export type CanvasObject = Record<string, unknown>;

/** Fabric.js の toJSON() 結果 + 正規化用メタ情報 */
export type CanvasData = {
  version?: string;
  /** 保存時のキャンバス幅（相対座標への変換基準） */
  canvas_width: number;
  /** 保存時のキャンバス高さ */
  canvas_height: number;
  objects: CanvasObject[];
};

/** アノテーション */
export type Annotation = {
  id: number;
  video_id: number;
  start_seconds: number;
  end_seconds: number;
  canvas_data: CanvasData;
  comment: string | null;
  comments_count?: number;
  created_at: string;
};

/** 切り抜きクリップ */
export type ClipStatus = 'processing' | 'done' | 'error';

export type Clip = {
  id: number;
  video_id: number;
  annotation_id: number | null;
  title: string;
  start_seconds: number;
  end_seconds: number;
  status: ClipStatus;
  download_url: string | null;
  created_at: string;
};

/** 共有リンク発行レスポンス */
export type ShareLinkResult = {
  token: string;
  share_url: string;
  expires_at: string | null;
  created_at: string;
};

/** 共有ページ取得レスポンス */
export type ShareView = {
  annotation: {
    id: number;
    start_seconds: number;
    end_seconds: number;
    canvas_data: CanvasData;
    comment: string | null;
  };
  video: {
    youtube_video_id: string;
    title: string;
  };
  expires_at: string | null;
};

/** ログインユーザー */
export type AuthUser = {
  id: number;
  name: string;
  email: string;
};

export type TeamMemberRole = 'owner' | 'member';

export type TeamMember = {
  id: number;
  name: string;
  email?: string;
  role: TeamMemberRole;
};

export type Team = {
  id: number;
  name: string;
  invite_token: string;
  invite_url: string;
  owner: {
    id: number;
    name: string;
  };
  members: TeamMember[];
  created_at: string;
};

export type Comment = {
  id: number;
  body: string;
  user: {
    id: number;
    name: string;
  };
  is_own: boolean;
  created_at: string;
};
