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
          <a href="#" style={{ fontSize: '13px', fontWeight: 600, color: '#0a4d3a', textDecoration: 'none', transition: 'color 0.2s' }}>
            Forgot password?
          </a>
        </div>

        <button 
          type="submit" 
          disabled={loading}
          style={{ width: '100%', height: '3rem', backgroundColor: '#0a4d3a', color: '#ffffff', fontSize: '14px', fontWeight: 600, borderRadius: '0.5rem', border: 'none', cursor: loading ? 'not-allowed' : 'pointer', transition: 'all 0.2s', display: 'flex', alignItems: 'center', justifyContent: 'center', marginTop: '0.75rem', boxShadow: '0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06)' }}
        >
          {loading ? 'Signing in...' : 'Sign In'}
        </button>

        <div style={{ position: 'relative', display: 'flex', alignItems: 'center', justifyContent: 'center', margin: '1rem 0' }}>
          <div style={{ position: 'absolute', inset: 0, display: 'flex', alignItems: 'center' }}>
            <div style={{ width: '100%', backgroundColor: '#e2e8f0', height: '1px' }}></div>
          </div>
          <span style={{ position: 'relative', padding: '0 0.75rem', backgroundColor: '#ffffff', fontSize: '12px', fontWeight: 500, color: '#64748b' }}>
            Or continue with
          </span>
        </div>

        <button type="button" style={{ width: '100%', height: '3rem', backgroundColor: '#ffffff', color: '#334155', fontSize: '14px', fontWeight: 600, borderRadius: '0.5rem', border: '1px solid #e2e8f0', cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '0.5rem', transition: 'all 0.2s', boxShadow: '0 1px 2px 0 rgba(0, 0, 0, 0.05)' }} onMouseOver={(e) => {e.currentTarget.style.backgroundColor = '#f8fafc'; e.currentTarget.style.borderColor = '#cbd5e1';}} onMouseOut={(e) => {e.currentTarget.style.backgroundColor = '#ffffff'; e.currentTarget.style.borderColor = '#e2e8f0';}}>
          <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24">
            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"></path>
            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"></path>
            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z" fill="#FBBC05"></path>
            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z" fill="#EA4335"></path>
          </svg>
          <span>Continue with Google</span>
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
