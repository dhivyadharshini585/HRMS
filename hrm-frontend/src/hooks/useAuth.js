import { useSelector } from 'react-redux';

export const useAuth = () => {
  const { user, token, isAuthenticated, roles, permissions } = useSelector((state) => state.auth);

  const hasRole = (allowedRoles) => {
    if (!roles) return false;
    return roles.some(role => allowedRoles.includes(role));
  };

  const hasPermission = (permission) => {
    if (!permissions) return false;
    return permissions.includes(permission);
  };

  return {
    user,
    token,
    isAuthenticated,
    roles,
    permissions,
    hasRole,
    hasPermission
  };
};
