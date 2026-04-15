export const WorkspaceRole = {
    Owner: 'owner',
    Admin: 'admin',
    Member: 'member',
    Viewer: 'viewer',
} as const;

export type WorkspaceRoleValue = (typeof WorkspaceRole)[keyof typeof WorkspaceRole];
