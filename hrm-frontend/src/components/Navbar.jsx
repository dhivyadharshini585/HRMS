import React from 'react';
import { Navbar, Nav, Container, Button } from 'react-bootstrap';
import { useDispatch, useSelector } from 'react-redux';
import { logout } from '../store/authSlice';
import { authService } from '../services/authService';
import { useNavigate } from 'react-router-dom';
import { LogOut } from 'lucide-react';

const TopNavbar = () => {
  const dispatch = useDispatch();
  const navigate = useNavigate();
  const { user } = useSelector((state) => state.auth);

  const handleLogout = async () => {
    try {
      await authService.logout();
    } catch (error) {
      console.error('Logout failed on server, but clearing local state anyway', error);
    } finally {
      dispatch(logout());
      navigate('/login');
    }
  };

  return (
    <Navbar bg="white" expand="lg" className="border-bottom shadow-sm">
      <Container fluid>
        <Navbar.Brand href="#home" className="fw-bold text-primary">
          HRMS Portal
        </Navbar.Brand>
        <Navbar.Toggle aria-controls="basic-navbar-nav" />
        <Navbar.Collapse id="basic-navbar-nav" className="justify-content-end">
          <Nav>
            <Nav.Link className="d-flex align-items-center me-3">
              <span className="text-muted me-2">Welcome,</span>
              <span className="fw-semibold">{user?.name || 'User'}</span>
            </Nav.Link>
            <Button variant="outline-danger" size="sm" onClick={handleLogout} className="d-flex align-items-center">
              <LogOut size={16} className="me-1" /> Logout
            </Button>
          </Nav>
        </Navbar.Collapse>
      </Container>
    </Navbar>
  );
};

export default TopNavbar;
