import type React from 'react';
import {
  hasPermission as _hasPermission,
  hasPermissions as _hasPermissions,
  hasAnyPermission as _hasAnyPermission,
} from '@/utils/permissions';

type RequireOnlyOnePermission =
  | {
      hasPermission: string;
      hasAnyPermission?: never;
      hasPermissions?: never;
    }
  | {
      hasPermission?: never;
      hasAnyPermission: string[];
      hasPermissions?: never;
    }
  | {
      hasPermission?: never;
      hasAnyPermission?: never;
      hasPermissions: string[];
    };

type PermissionCheckProps = RequireOnlyOnePermission & {
  denied?: React.ReactNode; // Content to render if permission is denied
  children: React.ReactNode; // Content to render if permission is granted
};

const PermissionCheck: React.FC<PermissionCheckProps> = ({
  hasPermission,
  hasAnyPermission,
  hasPermissions,
  denied = null,
  children,
}) => {
  const isAllowed =
    (hasPermission && _hasPermission(hasPermission)) ||
    (hasAnyPermission && _hasAnyPermission(hasAnyPermission)) ||
    (hasPermissions && _hasPermissions(hasPermissions));

  return <>{isAllowed ? children : denied}</>;
};

export default PermissionCheck;
