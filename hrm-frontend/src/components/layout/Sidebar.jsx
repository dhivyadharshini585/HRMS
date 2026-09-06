import { NavLink } from 'react-router-dom';
import { ROUTES } from '../../constants/routes';
import { 
  IconDashboard, IconUsers, IconFolder, IconBriefcase,
  IconCalendar, IconClock, IconFileText, IconDollar,
  IconTrendingUp, IconBook, IconPackage, IconHelp, IconShield,
  IconChevronLeft, IconChevronRight
} from '../common/Icons';
import { useAuthContext } from '../../context/AuthContext';
import { ROLE_NAVIGATION } from '../../config/roleConfig';

const ICONS = {
  IconDashboard: <IconDashboard />,
  IconUsers: <IconUsers />,
  IconFolder: <IconFolder />,
  IconBriefcase: <IconBriefcase />,
  IconCalendar: <IconCalendar />,
  IconClock: <IconClock />,
  IconFileText: <IconFileText />,
  IconDollar: <IconDollar />,
  IconTrendingUp: <IconTrendingUp />,
  IconBook: <IconBook />,
  IconPackage: <IconPackage />,
  IconHelp: <IconHelp />,
  IconShield: <IconShield />,
};

export default function Sidebar({ collapsed, setCollapsed, mobileOpen, setMobileOpen }) {
  const { getPrimaryRole } = useAuthContext();
  const primaryRole = getPrimaryRole();
  const navItems = ROLE_NAVIGATION[primaryRole] || ROLE_NAVIGATION['Employee'];

  const handleNavClick = () => {
    // Close mobile menu when a link is clicked
    if (mobileOpen) {
      setMobileOpen(false);
    }
  };

  return (
    <>
      {/* Mobile Backdrop */}
      <div 
        className={`sidebar-backdrop ${mobileOpen ? 'visible' : ''}`}
        onClick={() => setMobileOpen(false)}
      ></div>

      <aside className={`app-sidebar ${collapsed ? 'collapsed' : ''} ${mobileOpen ? 'mobile-open' : ''}`}>
        <div className="sidebar-logo">
          <div className="logo-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
            </svg>
          </div>
          <span className="nav-link-text">HRMS</span>
        </div>
        
        <nav className="sidebar-nav">
          {navItems.map((item) => (
            <NavLink 
              key={item.path} 
              to={item.path} 
              className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}
              onClick={handleNavClick}
            >
              <div className="nav-link-icon">
                {ICONS[item.iconName]}
              </div>
              <span className="nav-link-text">{item.label}</span>
            </NavLink>
          ))}
        </nav>

        <div className="sidebar-footer">
          <button 
            className="collapse-btn" 
            onClick={() => setCollapsed(!collapsed)}
            aria-label={collapsed ? "Expand sidebar" : "Collapse sidebar"}
          >
            {collapsed ? <IconChevronRight /> : <IconChevronLeft />}
          </button>
        </div>
      </aside>
    </>
  );
}
