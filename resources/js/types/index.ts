export interface AuthUser { id: number; name: string; email: string }

export interface SharedProps {
    auth: { user: AuthUser | null };
    flash: { error: string | null; success: string | null };
    [key: string]: unknown;
}
