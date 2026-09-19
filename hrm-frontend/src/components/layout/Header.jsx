import { IconMenu } from '../common/Icons';
import NotificationDropdown from '../notifications/NotificationDropdown';
import { useAuthContext } from '../../context/AuthContext';

export default function Header({ toggleMobileMenu }) {
  const { user, logout, getPrimaryRole } = useAuthContext();

  return (
    <header className="app-header">
      <div className="header-left">
        <button 
          className="icon-btn mobile-menu-btn" 
          onClick={toggleMobileMenu}
          aria-label="Toggle Menu"
        >
          <IconMenu />
        </button>
        <div className="header-title" style={{ fontWeight: 600, color: 'var(--text-primary)', marginRight: '2rem' }}>HRMS Portal</div>
        <div style={{ display: 'flex', alignItems: 'center', background: 'var(--bg-main)', border: '1px solid var(--border-color)', borderRadius: 'var(--radius-full)', padding: '0.35rem 1rem', width: '300px' }}>
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--text-muted)" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" style={{ marginRight: '0.5rem' }}>
            <circle cx="11" cy="11" r="8"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
          </svg>
          <input type="text" placeholder="Search employees, payroll, policies..." style={{ border: 'none', background: 'transparent', outline: 'none', fontSize: '0.75rem', width: '100%', color: 'var(--text-primary)' }} />
        </div>
      </div>
      
      <div className="header-right">
        <NotificationDropdown />
        
        <div className="user-profile">
          <div className="avatar">{user?.name?.charAt(0) || 'U'}</div>
          <div className="user-info">
            <span className="user-name">{user?.name || 'User'}</span>
            <span className="user-role">{getPrimaryRole()}</span>
          </div>
          <button 
            onClick={logout} 
            style={{ 
              marginLeft: '1rem', 
              padding: '0.35rem 0.75rem', 
              cursor: 'pointer', 
              border: '1px solid var(--border-color)', 
              borderRadius: 'var(--radius-md)', 
              background: 'var(--bg-surface)',
              fontSize: '0.75rem',
              fontWeight: '500'
            }}
          >
            Logout
          </button>
        </div>
      </div>
    </header>
  );
}
