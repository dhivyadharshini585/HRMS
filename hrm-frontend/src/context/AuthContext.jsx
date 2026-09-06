import { createContext, useCallback, useContext, useEffect, useState } from 'react';
import api from '../services/api';

export const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);

  const getCurrentUser = useCallback(async (options = {}) => {
    const token = localStorage.getItem('hrms_access_token');
    if (!token) {
      setUser(null);
      return null;
    }
    try {
      const response = await api.get('/user', options);
      setUser(response.data);
      return response.data;
    } catch (err) {
      if (err.name === 'CanceledError') {
        throw err;
      }
      setUser(null);
      localStorage.removeItem('hrms_access_token');
      return null;
    }
  }, []);

  useEffect(() => {
    const controller = new AbortController();
    getCurrentUser({ signal: controller.signal })
      .catch((err) => {
        if (err.name !== 'CanceledError') console.error(err);
      })
      .finally(() => {
        setLoading(false);
      });
      
    return () => { controller.abort(); };
  }, [getCurrentUser]);

  const login = async (credentials) => {
    const response = await api.post('/login', credentials);

    const { access_token, user: authenticatedUser } = response.data;

    localStorage.setItem('hrms_access_token', access_token);
    setUser(authenticatedUser);

    return authenticatedUser;
  };

  const logout = async () => {
    try {
      await api.post('/logout');
    } finally {
      localStorage.removeItem('hrms_access_token');
      setUser(null);
    }
  };

  const hasRole = (role) => {
    return user?.roles?.includes(role) || false;
  };

  const hasAnyRole = (roles) => {
    return user?.roles?.some(role => roles.includes(role)) || false;
  };

  const hasPermission = (permission) => {
    return user?.permissions?.includes(permission) || false;
  };

  const getPrimaryRole = () => {
    if (!user) return null;
    if (user.roles) {
      const rolesArray = Array.isArray(user.roles) ? user.roles : Object.values(user.roles);
      if (rolesArray.length > 0) {
        return rolesArray[0];
      }
    }
    return 'No Role';
  };

  const value = {
    user,
    loading,
    login,
    logout,
    hasRole,
    hasAnyRole,
    hasPermission,
    getPrimaryRole,
    getCurrentUser,
  };

  return (
    <AuthContext.Provider value={value}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuthContext() {
  return useContext(AuthContext);
}