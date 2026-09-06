import '../../styles/auth.css';

export default function AuthLayout({ children }) {
  return (
    <div className="auth-page">
      <div className="auth-header">
        <div className="auth-brand">HRMS</div>
        <div className="auth-brand-subtitle">Human Resource Management System</div>
      </div>
      
      <main className="auth-card">
        {children}
      </main>
    </div>
  );
}
