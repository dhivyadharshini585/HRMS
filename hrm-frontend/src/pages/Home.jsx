import React from 'react';
import { Navigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import Landing from './Landing';
import Dashboard from './Dashboard';
import { ROUTES } from '../constants/routes';

const Home = () => {
  const { user, loading } = useAuth();
  
  if (loading) {
    return <div>Loading...</div>;
  }

  // Check if we are on the central domain or a tenant subdomain
  const hostname = window.location.hostname;
  const isCentralDomain = hostname === 'localhost' || hostname === 'hrms.local';

  if (user) {
    return <Dashboard />;
  } else {
    if (isCentralDomain) {
      return <Landing />;
    } else {
      return <Navigate to={ROUTES.LOGIN} />;
    }
  }
};

export default Home;
