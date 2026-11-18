export interface AuthUser { id: number; name: string; email: string }

export interface SharedProps {
    auth: { user: AuthUser | null };
    flash: { error: string | null; success: string | null };
    [key: string]: unknown;
}

export type ReadinessState = 'empty' | 'processing' | 'attention' | 'ready';

export interface RhkReadiness { state: ReadinessState; ready: number; processing: number; failed: number; total: number }
export interface PeriodReadiness { rhk_total: number; rhk_ready: number; percent: number }

export interface EvidentItem {
    id: string; title: string; type: 'file_upload' | 'external_link';
    file_name: string | null; file_size: number | null;
    status: 'processing' | 'ready' | 'failed';
    error: string | null; reauth_required: boolean; is_protected: boolean;
    links: { direct: string | null; short: string };
    expires_at: string | null; expired: boolean;
    drive_link: string | null; external_url: string | null; created_at: string;
}

export interface ViewerItem {
    id: string; title: string; kind: 'pdf' | 'image' | 'file' | 'link';
    file_name?: string | null; file_size?: number | null;
    preview_url?: string | null; download_url?: string; open_url: string | null;
}

export interface ShareInfo { url: string; is_protected: boolean; expires_at: string | null; expired: boolean }
