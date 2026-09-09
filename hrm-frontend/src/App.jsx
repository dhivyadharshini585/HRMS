import React from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { useSelector } from 'react-redux';

import GlobalToast from './components/GlobalToast';
import ProtectedRoute from './routes/ProtectedRoute';
import RoleRoute from './routes/RoleRoute';

import AuthLayout from './layouts/AuthLayout';
import AdminLayout from './layouts/AdminLayout';
import EmployeeLayout from './layouts/EmployeeLayout';

import Login from './pages/auth/Login';
import AdminDashboard from './pages/dashboard/AdminDashboard';
import EmployeeList from './pages/employees/EmployeeList';

const EmployeeDashboard = () => <div><h3>My Dashboard</h3><p>Self-service content here</p></div>;

function App() {
  const { isAuthenticated, roles } = useSelector(state => state.auth);
  
  // Basic logic to determine homepage based on roles
  const getHomeRoute = () => {
    if (!isAuthenticated) return '/login';
    if (roles.includes('Super Admin') || roles.includes('HR Admin') || roles.includes('HR Executive')) {
      return '/admin/dashboard';
    }
    return '/employee/dashboard';
  };

  return (
    <>
      <GlobalToast />
      <BrowserRouter>
        <Routes>
          {/* Public Route */}
          <Route element={<AuthLayout />}>
            <Route path="/login" element={<Login />} />
          </Route>

          {/* Root Redirect */}
          <Route path="/" element={<Navigate to={getHomeRoute()} replace />} />

          {/* Protected Routes */}
          <Route element={<ProtectedRoute />}>
            
            {/* Admin Routes */}
            <Route element={<RoleRoute allowedRoles={['Super Admin', 'HR Admin', 'HR Executive']} />}>
              <Route path="/admin" element={<AdminLayout />}>
                <Route path="dashboard" element={<AdminDashboard />} />
                <Route path="employees" element={<EmployeeList />} />
                {/* Add other admin routes here */}
              </Route>
            </Route>

            {/* Employee Routes */}
            <Route element={<RoleRoute allowedRoles={['Employee', 'Manager', 'Team Lead', 'Super Admin', 'HR Admin']} />}>
              <Route path="/employee" element={<EmployeeLayout />}>
                <Route path="dashboard" element={<EmployeeDashboard />} />
                {/* Add other employee routes here */}
              </Route>
            </Route>
            
          </Route>
        </Routes>
      </BrowserRouter>
    </>
  );
}

export default App;
