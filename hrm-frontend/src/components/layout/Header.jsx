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
        <div className="header-title">HRMS Portal</div>
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
