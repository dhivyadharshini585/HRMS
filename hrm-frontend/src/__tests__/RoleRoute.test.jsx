import React from 'react';
import { render, screen } from '@testing-library/react';
import { Provider } from 'react-redux';
import { MemoryRouter, Routes, Route } from 'react-router-dom';
import store from '../store';
import { loginSuccess, logout } from '../store/authSlice';
import RoleRoute from '../routes/RoleRoute';
import { describe, it, expect, beforeEach } from 'vitest';

describe('RoleRoute', () => {
  beforeEach(() => {
    store.dispatch(logout());
  });

  const renderWithRouterAndRedux = (ui, initialEntries = ['/']) => {
    return render(
      <Provider store={store}>
        <MemoryRouter initialEntries={initialEntries}>
          {ui}
        </MemoryRouter>
      </Provider>
    );
  };

  it('redirects to /login if user is not authenticated', () => {
    renderWithRouterAndRedux(
      <Routes>
        <Route path="/login" element={<div>Login Page</div>} />
        <Route element={<RoleRoute allowedRoles={['Super Admin']} />}>
          <Route path="/admin" element={<div>Admin Page</div>} />
        </Route>
      </Routes>,
      ['/admin']
    );

    expect(screen.getByText('Login Page')).toBeInTheDocument();
    expect(screen.queryByText('Admin Page')).not.toBeInTheDocument();
  });

  it('renders content if user has the correct role', () => {
    // Authenticate user with Super Admin role
    store.dispatch(loginSuccess({ token: '123', user: { name: 'Admin' }, roles: ['Super Admin'] }));

    renderWithRouterAndRedux(
      <Routes>
        <Route path="/unauthorized" element={<div>Unauthorized Page</div>} />
        <Route element={<RoleRoute allowedRoles={['Super Admin']} />}>
          <Route path="/admin" element={<div>Admin Page</div>} />
        </Route>
      </Routes>,
      ['/admin']
    );

    expect(screen.getByText('Admin Page')).toBeInTheDocument();
    expect(screen.queryByText('Unauthorized Page')).not.toBeInTheDocument();
  });

  it('redirects to /unauthorized if user lacks the required role', () => {
    // Authenticate user with Employee role only
    store.dispatch(loginSuccess({ token: '123', user: { name: 'Emp' }, roles: ['Employee'] }));

    renderWithRouterAndRedux(
      <Routes>
        <Route path="/unauthorized" element={<div>Unauthorized Page</div>} />
        <Route element={<RoleRoute allowedRoles={['Super Admin', 'HR Admin']} />}>
          <Route path="/admin" element={<div>Admin Page</div>} />
        </Route>
      </Routes>,
      ['/admin']
    );

    expect(screen.getByText('Unauthorized Page')).toBeInTheDocument();
    expect(screen.queryByText('Admin Page')).not.toBeInTheDocument();
  });
});
