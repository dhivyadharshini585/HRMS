import React, { useState } from 'react';
import { Form, Button, Alert, Spinner } from 'react-bootstrap';
import { useDispatch } from 'react-redux';
import { loginSuccess } from '../../store/authSlice';
import { authService } from '../../services/authService';
import { useNavigate, useLocation } from 'react-router-dom';
import { LogIn } from 'lucide-react';

const Login = () => {
  const [email, setEmail] = useState('admin@example.com');
  const [password, setPassword] = useState('password');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  
  const dispatch = useDispatch();
  const navigate = useNavigate();
  const location = useLocation();

  const from = location.state?.from?.pathname || '/admin/dashboard';

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setLoading(true);

    try {
      const data = await authService.login(email, password);
      // Assuming the backend returns { token: '...', user: {...}, roles: ['Super Admin'] }
      dispatch(loginSuccess({
        token: data.token,
        user: data.user,
        roles: data.roles || ['Super Admin'], // Defaulting for testing if backend doesn't send yet
        permissions: data.permissions || []
      }));
      navigate(from, { replace: true });
    } catch (err) {
      setError(err.response?.data?.message || 'Invalid email or password');
    } finally {
      setLoading(false);
    }
  };

  return (
    <>
      <div className="text-center mb-4">
        <h3 className="fw-bold text-primary">HRMS Portal</h3>
        <p className="text-muted">Sign in to your account</p>
      </div>

      {error && <Alert variant="danger">{error}</Alert>}

      <Form onSubmit={handleSubmit}>
        <Form.Group className="mb-3" controlId="formBasicEmail">
          <Form.Label>Email address</Form.Label>
          <Form.Control 
            type="email" 
            placeholder="Enter email" 
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            required
          />
        </Form.Group>

        <Form.Group className="mb-4" controlId="formBasicPassword">
          <Form.Label>Password</Form.Label>
          <Form.Control 
            type="password" 
            placeholder="Password" 
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
          />
        </Form.Group>

        <Button variant="primary" type="submit" className="w-100 py-2 d-flex justify-content-center align-items-center" disabled={loading}>
          {loading ? <Spinner animation="border" size="sm" /> : <><LogIn size={18} className="me-2" /> Sign In</>}
        </Button>
      </Form>
    </>
  );
};

export default Login;
