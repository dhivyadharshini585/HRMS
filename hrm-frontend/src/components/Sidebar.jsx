import React from 'react';
import { Nav } from 'react-bootstrap';
import { NavLink } from 'react-router-dom';
import { Users, LayoutDashboard, Calendar, FileText, Briefcase } from 'lucide-react';

const Sidebar = ({ isEmployee = false }) => {
  return (
    <div className="bg-dark text-white h-100 p-3" style={{ width: '250px', minHeight: '100vh' }}>
      <div className="mb-4 text-center mt-2">
        <h5>{isEmployee ? 'Self Service' : 'Administration'}</h5>
      </div>
      <Nav className="flex-column gap-2">
        {!isEmployee ? (
          <>
            <Nav.Link as={NavLink} to="/admin/dashboard" className="text-white d-flex align-items-center p-2 rounded auth-nav-link">
              <LayoutDashboard size={20} className="me-3" /> Dashboard
            </Nav.Link>
            <Nav.Link as={NavLink} to="/admin/employees" className="text-white d-flex align-items-center p-2 rounded auth-nav-link">
              <Users size={20} className="me-3" /> Employees
            </Nav.Link>
            <Nav.Link as={NavLink} to="/admin/attendance" className="text-white d-flex align-items-center p-2 rounded auth-nav-link">
              <Calendar size={20} className="me-3" /> Attendance
            </Nav.Link>
            <Nav.Link as={NavLink} to="/admin/leave" className="text-white d-flex align-items-center p-2 rounded auth-nav-link">
              <FileText size={20} className="me-3" /> Leave
            </Nav.Link>
            <Nav.Link as={NavLink} to="/admin/recruitment" className="text-white d-flex align-items-center p-2 rounded auth-nav-link">
              <Briefcase size={20} className="me-3" /> Recruitment
            </Nav.Link>
          </>
        ) : (
          <>
            <Nav.Link as={NavLink} to="/employee/dashboard" className="text-white d-flex align-items-center p-2 rounded auth-nav-link">
              <LayoutDashboard size={20} className="me-3" /> My Dashboard
            </Nav.Link>
            <Nav.Link as={NavLink} to="/employee/profile" className="text-white d-flex align-items-center p-2 rounded auth-nav-link">
              <Users size={20} className="me-3" /> My Profile
            </Nav.Link>
            <Nav.Link as={NavLink} to="/employee/leave" className="text-white d-flex align-items-center p-2 rounded auth-nav-link">
              <FileText size={20} className="me-3" /> My Leave
            </Nav.Link>
          </>
        )}
      </Nav>
    </div>
  );
};

export default Sidebar;
