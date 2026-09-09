import React from 'react';
import { Navigate, Outlet } from 'react-router-dom';
import { useSelector } from 'react-redux';

const RoleRoute = ({ allowedRoles }) => {
  const { roles, isAuthenticated } = useSelector((state) => state.auth);

  if (!isAuthenticated) {
    return <Navigate to="/login" replace />;
  }

  // Check if user has any of the allowed roles
  const hasRole = roles.some(role => allowedRoles.includes(role));

  if (!hasRole) {
    // Redirect to unauthorized page or default dashboard
    return <Navigate to="/unauthorized" replace />;
  }

  return <Outlet />;
};

export default RoleRoute;
