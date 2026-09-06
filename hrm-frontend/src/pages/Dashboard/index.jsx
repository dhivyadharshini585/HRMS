import { useAuthContext } from '../../context/AuthContext';
import { DASHBOARD_SECTIONS } from '../../config/roleConfig';

export default function Dashboard() {
  const { user, getPrimaryRole } = useAuthContext();
  const primaryRole = getPrimaryRole();
  const sections = DASHBOARD_SECTIONS[primaryRole] || DASHBOARD_SECTIONS['Employee'];

  return (
    <div>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '1.5rem' }}>
        <h1 className="page-title">Dashboard</h1>
        <span style={{ 
          padding: '0.25rem 0.75rem', 
          backgroundColor: 'var(--bg-surface)', 
          border: '1px solid var(--border-color)', 
          borderRadius: 'var(--radius-md)', 
          fontSize: '0.875rem', 
          fontWeight: '500',
          color: 'var(--text-secondary)'
        }}>
          Role: {primaryRole}
        </span>
      </div>
      <p className="page-subtitle" style={{ marginBottom: '2rem' }}>Welcome back, {user?.name}. Here's what's happening today.</p>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(300px, 1fr))', gap: '1.5rem' }}>
        {sections.map((section, index) => (
          <div key={index} style={{
            backgroundColor: 'var(--bg-surface)',
            border: '1px solid var(--border-color)',
            borderRadius: 'var(--radius-lg)',
            padding: '1.5rem',
            boxShadow: 'var(--shadow-sm)',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            minHeight: '120px'
          }}>
            <h3 style={{ fontSize: '1rem', fontWeight: '500', color: 'var(--text-secondary)' }}>{section}</h3>
          </div>
        ))}
      </div>
    </div>
  );
}
