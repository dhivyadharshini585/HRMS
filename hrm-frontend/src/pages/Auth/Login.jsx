import { useState } from 'react';
import { useNavigate, useLocation, Link } from 'react-router-dom';
import AuthLayout from '../../components/auth/AuthLayout';
import AuthInput from '../../components/auth/AuthInput';
import PasswordInput from '../../components/auth/PasswordInput';
import GoogleButton from '../../components/auth/GoogleButton';
import { ROUTES } from '../../constants/routes';
import { useAuthContext } from '../../context/AuthContext';

export default function Login() {
  const navigate = useNavigate();
  const location = useLocation();
  const { login } = useAuthContext();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [errors, setErrors] = useState({});
  const [apiError, setApiError] = useState('');
  const [loading, setLoading] = useState(false);

  const validate = () => {
    const newErrors = {};
    if (!email) newErrors.email = 'Email is required';
    else if (!/\S+@\S+\.\S+/.test(email)) newErrors.email = 'Invalid email address';
    if (!password) newErrors.password = 'Password is required';
    return newErrors;
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (loading) return;

    const validationErrors = validate();
    if (Object.keys(validationErrors).length > 0) {
      setErrors(validationErrors);
      return;
    }

    setLoading(true);
    setApiError('');
    setErrors({});

    try {
      await login({ email, password });
      navigate(ROUTES.DASHBOARD);
    } catch (err) {
      if (err.response?.status === 422) {
        setErrors(err.response.data.errors || {});
        if (err.response.data.message) {
            setApiError(err.response.data.message);
        }
      } else if (err.response?.status === 401) {
        setApiError('Invalid credentials or unauthenticated.');
      } else {
        setApiError('An unexpected error occurred. Please try again.');
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <AuthLayout>
      <h1 className="auth-title">Welcome back</h1>
      <p className="auth-subtitle">Sign in to access your HRMS account.</p>
      <p className="auth-subtitle" style={{ marginTop: '-0.5rem', marginBottom: '2rem', fontSize: '0.875rem' }}>Use the credentials provided by your organization.</p>

      {location.state?.message && (
        <div style={{ marginBottom: '1rem', padding: '0.75rem', backgroundColor: '#ecfdf5', color: '#065f46', border: '1px solid #10b981', borderRadius: 'var(--radius-md)', fontSize: '0.875rem' }}>
          {location.state.message}
        </div>
      )}

      {apiError && (
        <div style={{ marginBottom: '1rem', padding: '0.75rem', backgroundColor: '#fef2f2', color: '#b91c1c', borderRadius: 'var(--radius-md)', fontSize: '0.875rem' }}>
          {apiError}
        </div>
      )}

      <form className="auth-form" onSubmit={handleSubmit}>
        <div style={{ position: 'relative' }}>
          <AuthInput
            label="Email"
            id="email"
            type="email"
            placeholder="name@company.com"
            value={email}
            onChange={(e) => {
              setEmail(e.target.value);
              if (errors.email) setErrors({ ...errors, email: '' });
            }}
            error={errors.email ? errors.email[0] || errors.email : ''}
            required
          />
        </div>

        <PasswordInput
          label="Password"
          id="password"
          placeholder="••••••••"
          value={password}
          onChange={(e) => {
            setPassword(e.target.value);
            if (errors.password) setErrors({ ...errors, password: '' });
          }}
          error={errors.password ? errors.password[0] || errors.password : ''}
          required
        />

        <div className="auth-options">
          <label className="remember-me">
            <input type="checkbox" />
            <span>Remember me</span>
          </label>
          <a href="#" className="forgot-password">
            Forgot password?
          </a>
        </div>

        <button type="submit" className="btn btn-primary btn-block" disabled={loading}>
          {loading ? 'Signing in...' : 'Sign In'}
        </button>
      </form>

      <div className="auth-divider">
        <span>Or continue with</span>
      </div>

      <div className="auth-social">
        <GoogleButton onClick={() => console.log('Google login clicked - Not implemented')} />
      </div>

      <p className="auth-footer">
        Need access?{' '}
        <span style={{ color: 'var(--primary-color)', fontWeight: '500' }}>Contact your HR administrator.</span>
      </p>
    </AuthLayout>
  );
}
