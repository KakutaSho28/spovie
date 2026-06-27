import { BrowserRouter, Navigate, Outlet, Route, Routes } from 'react-router-dom';
import { useAuthStore } from './store/auth';
import { Layout } from './components/Layout';
import { LoginPage } from './pages/Login';
import { RegisterPage } from './pages/Register';
import { VideosPage } from './pages/Videos';
import { VideoNewPage } from './pages/VideoNew';
import { AnnotationsPage } from './pages/Annotations';
import { AnnotatePage } from './pages/Annotate';
import { ClipStatusPage } from './pages/ClipStatus';
import { SharePage } from './pages/Share';
import { TeamsPage } from './pages/Teams';
import { TeamNewPage } from './pages/TeamNew';
import { TeamDetailPage } from './pages/TeamDetail';
import { TeamJoinPage } from './pages/TeamJoin';

/** 認証ガード：未ログインなら S01 へ */
function RequireAuth() {
  const token = useAuthStore((s) => s.token);
  return token ? <Outlet /> : <Navigate to="/login" replace />;
}

/** 逆ガード：ログイン済みなら S03 へ */
function GuestOnly() {
  const token = useAuthStore((s) => s.token);
  return token ? <Navigate to="/" replace /> : <Outlet />;
}

export function App() {
  return (
    <BrowserRouter>
      <Routes>
        <Route element={<GuestOnly />}>
          <Route path="/login" element={<LoginPage />} />
          <Route path="/register" element={<RegisterPage />} />
        </Route>

        {/* S07 共有ページ（認証不要） */}
        <Route path="/share/:token" element={<SharePage />} />

        <Route element={<RequireAuth />}>
          <Route element={<Layout />}>
            <Route path="/" element={<VideosPage />} />
            <Route path="/videos/new" element={<VideoNewPage />} />
            <Route path="/videos/:videoId/annotations" element={<AnnotationsPage />} />
            <Route path="/videos/:videoId/annotate" element={<AnnotatePage />} />
            <Route path="/clips/:clipId" element={<ClipStatusPage />} />
            <Route path="/teams" element={<TeamsPage />} />
            <Route path="/teams/new" element={<TeamNewPage />} />
            <Route path="/teams/join/:token" element={<TeamJoinPage />} />
            <Route path="/teams/:teamId" element={<TeamDetailPage />} />
          </Route>
        </Route>

        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
    </BrowserRouter>
  );
}
