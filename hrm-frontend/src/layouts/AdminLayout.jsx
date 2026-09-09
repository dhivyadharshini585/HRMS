import React from 'react';
import { Outlet } from 'react-router-dom';
import Sidebar from '../components/Sidebar';
import TopNavbar from '../components/Navbar';

const AdminLayout = () => {
  return (
    <div className="d-flex" style={{ minHeight: '100vh', overflow: 'hidden' }}>
      <Sidebar isEmployee={false} />
      <div className="flex-grow-1 d-flex flex-column bg-light" style={{ overflowY: 'auto', height: '100vh' }}>
        <TopNavbar />
        <main className="p-4 flex-grow-1">
          <Outlet />
        </main>
      </div>
    </div>
  );
};

export default AdminLayout;
