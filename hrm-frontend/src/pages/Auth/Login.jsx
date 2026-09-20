import { useState } from 'react';
import { useNavigate, useLocation, Link } from 'react-router-dom';
import AuthLayout from '../../components/auth/AuthLayout';
import AuthInput from '../../components/auth/AuthInput';

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
  const [passwordVisible, setPasswordVisible] = useState(false);

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
      <div style={{ textAlign: 'center', marginBottom: '2rem' }}>
        <h2 style={{ fontSize: '26px', lineHeight: '34px', fontWeight: 700, color: '#0a4d3a', letterSpacing: '-0.025em', fontFamily: '"Plus Jakarta Sans", sans-serif' }}>Welcome back</h2>
        <p style={{ fontSize: '13px', lineHeight: '20px', fontWeight: 400, color: '#64748b', marginTop: '0.5rem' }}>
          Sign in to access your HRMS account.<br/>Use the credentials provided by your organization.
        </p>
      </div>

      {location.state?.message && (
        <div style={{ marginBottom: '1rem', padding: '0.75rem', backgroundColor: '#ecfdf5', color: '#065f46', border: '1px solid #10b981', borderRadius: '0.5rem', fontSize: '0.875rem' }}>
          {location.state.message}
        </div>
      )}

      {apiError && (
        <div style={{ marginBottom: '1rem', padding: '0.75rem', backgroundColor: '#fef2f2', color: '#b91c1c', borderRadius: '0.5rem', fontSize: '0.875rem' }}>
          {apiError}
        </div>
      )}

      <form className="auth-form" onSubmit={handleSubmit} style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
        
        <div style={{ display: 'flex', flexDirection: 'column', gap: '0.5rem' }}>
          <label htmlFor="email" style={{ fontSize: '13px', fontWeight: 500, color: '#334155', fontFamily: 'Inter, sans-serif' }}>Email</label>
          <div style={{ position: 'relative' }}>
            <input 
              id="email" 
              type="email" 
              placeholder="name@company.com" 
              value={email}
              onChange={(e) => {
                setEmail(e.target.value);
                if (errors.email) setErrors({ ...errors, email: '' });
              }}
              style={{ width: '100%', height: '2.875rem', padding: '0 1rem', backgroundColor: '#ffffff', color: '#0f172a', fontSize: '14px', borderRadius: '0.5rem', outline: 'none', border: errors.email ? '1px solid #ef4444' : '1px solid #e2e8f0', transition: 'all 0.2s', boxShadow: '0 1px 2px 0 rgba(0, 0, 0, 0.05)' }}
              onFocus={(e) => { e.target.style.borderColor = '#0a4d3a'; e.target.style.boxShadow = '0 0 0 1px #0a4d3a'; }}
              onBlur={(e) => { e.target.style.borderColor = errors.email ? '#ef4444' : '#e2e8f0'; e.target.style.boxShadow = '0 1px 2px 0 rgba(0, 0, 0, 0.05)'; }}
              required 
            />
            {errors.email && <div style={{ fontSize: '0.75rem', color: '#ef4444', marginTop: '0.25rem' }}>{errors.email[0] || errors.email}</div>}
          </div>
        </div>

        <div style={{ display: 'flex', flexDirection: 'column', gap: '0.5rem' }}>
          <label htmlFor="password" style={{ fontSize: '13px', fontWeight: 500, color: '#334155', fontFamily: 'Inter, sans-serif' }}>Password</label>
          <div style={{ position: 'relative', display: 'flex', alignItems: 'center' }}>
            <input 
              id="password" 
              type={passwordVisible ? 'text' : 'password'}
              placeholder="••••••••" 
              value={password}
              onChange={(e) => {
                setPassword(e.target.value);
                if (errors.password) setErrors({ ...errors, password: '' });
              }}
              style={{ width: '100%', height: '2.875rem', padding: '0 2.5rem 0 1rem', backgroundColor: '#ffffff', color: '#0f172a', fontSize: '14px', borderRadius: '0.5rem', outline: 'none', border: errors.password ? '1px solid #ef4444' : '1px solid #e2e8f0', transition: 'all 0.2s', boxShadow: '0 1px 2px 0 rgba(0, 0, 0, 0.05)' }}
              onFocus={(e) => { e.target.style.borderColor = '#0a4d3a'; e.target.style.boxShadow = '0 0 0 1px #0a4d3a'; }}
              onBlur={(e) => { e.target.style.borderColor = errors.password ? '#ef4444' : '#e2e8f0'; e.target.style.boxShadow = '0 1px 2px 0 rgba(0, 0, 0, 0.05)'; }}
              required 
            />
            <button 
              type="button"
              onClick={() => setPasswordVisible(!passwordVisible)}
              style={{ position: 'absolute', right: '0.75rem', color: '#94a3b8', background: 'transparent', border: 'none', cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center' }}
            >
              {passwordVisible ? (
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                  <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                  <line x1="1" y1="1" x2="23" y2="23"></line>
                </svg>
              ) : (
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                  <circle cx="12" cy="12" r="3"></circle>
                </svg>
              )}
            </button>
          </div>
          {errors.password && <div style={{ fontSize: '0.75rem', color: '#ef4444', marginTop: '0.25rem' }}>{errors.password[0] || errors.password}</div>}
        </div>

        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', paddingTop: '0.25rem' }}>
          <label style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', cursor: 'pointer', userSelect: 'none' }}>
            <input type="checkbox" style={{ width: '1rem', height: '1rem', borderRadius: '0.25rem', border: '1px solid #cbd5e1', backgroundColor: '#ffffff', color: '#0a4d3a', cursor: 'pointer' }} />
            <span style={{ fontSize: '13px', color: '#64748b' }}>Remember me</span>
          </label>
          <Link to={ROUTES.ADMIN_RECOVERY} style={{ fontSize: '13px', fontWeight: 600, color: '#0a4d3a', textDecoration: 'none' }}>
            Forgot password?
          </Link>
        </div>

        <button 
          type="submit" 
          disabled={loading}
          style={{ width: '100%', height: '3rem', backgroundColor: '#0a4d3a', color: '#ffffff', fontSize: '14px', fontWeight: 600, borderRadius: '0.5rem', border: 'none', cursor: loading ? 'not-allowed' : 'pointer', transition: 'all 0.2s', display: 'flex', alignItems: 'center', justifyContent: 'center', marginTop: '0.75rem', boxShadow: '0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06)' }}
        >
          {loading ? 'Signing in...' : 'Sign In'}
        </button>
      </form>

      <div style={{ textAlign: 'center', marginTop: '1.5rem' }}>
        <p style={{ fontSize: '13px', color: '#64748b' }}>
          Need access?
          <a href="#" style={{ fontSize: '13px', fontWeight: 600, color: '#0a4d3a', textDecoration: 'none', marginLeft: '0.25rem' }}>
            Contact your HR administrator.
          </a>
        </p>
      </div>
    </AuthLayout>
  );
}
