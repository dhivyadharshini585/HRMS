import { createBrowserRouter } from 'react-router-dom';
import { ROUTES } from '../constants/routes';

// Layout
import AppLayout from '../components/layout/AppLayout';
import ProtectedRoute from '../components/auth/ProtectedRoute';
import GuestRoute from '../components/auth/GuestRoute';

// Auth pages
import Login from '../pages/Auth/Login';
import Register from '../pages/Auth/Register';
import ForgotPassword from '../pages/Auth/ForgotPassword';

// Module pages
import Dashboard from '../pages/Dashboard';
import Employees from '../pages/Employees';
import EmployeeForm from '../pages/Employees/EmployeeForm';
import EmployeeView from '../pages/Employees/EmployeeView';
import Departments from '../pages/Departments';
import DepartmentForm from '../pages/Departments/DepartmentForm';
import Designations from '../pages/Designations';
import DesignationForm from '../pages/Designations/DesignationForm';
import Attendance from '../pages/Attendance';
import Leave from '../pages/Leave';
import Jobs from '../pages/Jobs';
import Candidates from '../pages/Candidates';
import Recruitment from '../pages/Recruitment';
import Payroll from '../pages/Payroll';
import Performance from '../pages/Performance';
import Training from '../pages/Training';
import Assets from '../pages/Assets';
import Timesheets from '../pages/Timesheets';
import Helpdesk from '../pages/Helpdesk';
import Policies from '../pages/Policies';
import Documents from '../pages/Documents';
import AuditLogs from '../pages/AuditLogs';
import Shifts from '../pages/Shifts';
import Holidays from '../pages/Holidays';
import AttendanceReport from '../pages/Reports/AttendanceReport';
import LeaveReport from '../pages/Reports/LeaveReport';
import Interviews from '../pages/Interviews';
import OfferLetters from '../pages/OfferLetters';

export const router = createBrowserRouter([
  // Auth routes (outside AppLayout, guest only)
  {
    element: <GuestRoute />,
    children: [
      { path: ROUTES.LOGIN, element: <Login /> },
      { path: ROUTES.FORGOT_PASSWORD, element: <ForgotPassword /> },
    ]
  },

  // Module routes (wrapped in AppLayout and protected)
  {
    element: <ProtectedRoute />, // Base protection: Must be logged in
    children: [
      {
        element: <AppLayout />,
        children: [
          // DASHBOARD: all six roles
          { path: ROUTES.DASHBOARD, element: <Dashboard /> },

          // SELF PROFILE: all six roles
          { path: ROUTES.EMPLOYEE_ME, element: <EmployeeView /> },

          // EMPLOYEES DIRECTORY & CRUD: Super Admin, HR Admin, HR Executive, Manager, Finance/Payroll Admin
          { 
            element: <ProtectedRoute roles={['Super Admin', 'HR Admin', 'HR Executive', 'Manager', 'Finance/Payroll Admin']} />,
            children: [
              { path: ROUTES.EMPLOYEES, element: <Employees /> },
              { path: ROUTES.EMPLOYEE_ADD, element: <EmployeeForm /> },
              { path: ROUTES.EMPLOYEE_VIEW, element: <EmployeeView /> },
              { path: ROUTES.EMPLOYEE_EDIT, element: <EmployeeForm /> },
            ]
          },

          // DEPARTMENTS: Super Admin, HR Admin, HR Executive
          { 
            element: <ProtectedRoute roles={['Super Admin', 'HR Admin', 'HR Executive']} />,
            children: [
              { path: ROUTES.DEPARTMENTS, element: <Departments /> },
              { path: ROUTES.DEPARTMENT_ADD, element: <DepartmentForm /> },
              { path: ROUTES.DEPARTMENT_EDIT, element: <DepartmentForm /> },
            ]
          },

          // DESIGNATIONS: Super Admin, HR Admin, HR Executive
          { 
            element: <ProtectedRoute roles={['Super Admin', 'HR Admin', 'HR Executive']} />,
            children: [
              { path: ROUTES.DESIGNATIONS, element: <Designations /> },
              { path: ROUTES.DESIGNATION_ADD, element: <DesignationForm /> },
              { path: ROUTES.DESIGNATION_EDIT, element: <DesignationForm /> },
            ]
          },

          // DOCUMENTS: Super Admin, HR Admin, HR Executive, Manager, Employee
          { 
            element: <ProtectedRoute roles={['Super Admin', 'HR Admin', 'HR Executive', 'Manager', 'Employee']} />,
            children: [{ path: ROUTES.DOCUMENTS, element: <Documents /> }]
          },

          // AUDIT LOGS: Super Admin, HR Admin, Finance/Payroll Admin
          { 
            element: <ProtectedRoute roles={['Super Admin', 'HR Admin', 'Finance/Payroll Admin']} />,
            children: [{ path: ROUTES.AUDIT_LOGS, element: <AuditLogs /> }]
          },

          // SHIFTS: Super Admin, HR Admin, HR Executive, Manager (Employee excluded)
          { 
            element: <ProtectedRoute roles={['Super Admin', 'HR Admin', 'HR Executive', 'Manager']} />,
            children: [{ path: ROUTES.SHIFTS, element: <Shifts /> }]
          },

          // HOLIDAYS: Super Admin, HR Admin, HR Executive, Manager, Employee
          { 
            element: <ProtectedRoute roles={['Super Admin', 'HR Admin', 'HR Executive', 'Manager', 'Employee']} />,
            children: [{ path: ROUTES.HOLIDAYS, element: <Holidays /> }]
          },

          // ATTENDANCE: all six roles
          { path: ROUTES.ATTENDANCE, element: <Attendance /> },

          // LEAVE: all six roles
          { path: ROUTES.LEAVE, element: <Leave /> },

          // ATTENDANCE & LEAVE REPORTS: Super Admin, HR Admin, HR Executive, Manager, Employee (Finance/Payroll Admin excluded)
          {
            element: <ProtectedRoute roles={['Super Admin', 'HR Admin', 'HR Executive', 'Manager', 'Employee']} />,
            children: [
              { path: ROUTES.REPORTS_ATTENDANCE, element: <AttendanceReport /> },
              { path: ROUTES.REPORTS_LEAVE, element: <LeaveReport /> },
            ]
          },

          // JOB OPENINGS / CANDIDATES / RECRUITMENT: Super Admin, HR Admin, HR Executive
          { 
            element: <ProtectedRoute roles={['Super Admin', 'HR Admin', 'HR Executive']} />,
            children: [
              { path: ROUTES.JOBS, element: <Jobs /> },
              { path: ROUTES.CANDIDATES, element: <Candidates /> },
              { path: ROUTES.RECRUITMENT, element: <Jobs /> },
              { path: ROUTES.OFFER_LETTERS, element: <OfferLetters /> },
            ]
          },
 
          // INTERVIEWS: Super Admin, HR Admin, HR Executive, Manager
          { 
            element: <ProtectedRoute roles={['Super Admin', 'HR Admin', 'HR Executive', 'Manager']} />,
            children: [{ path: ROUTES.INTERVIEWS, element: <Interviews /> }]
          },

          // PAYROLL: Super Admin, Finance/Payroll Admin, HR Admin
          { 
            element: <ProtectedRoute roles={['Super Admin', 'Finance/Payroll Admin', 'HR Admin']} />,
            children: [{ path: ROUTES.PAYROLL, element: <Payroll /> }]
          },

          // PERFORMANCE: Super Admin, HR Admin, Manager, Employee
          { 
            element: <ProtectedRoute roles={['Super Admin', 'HR Admin', 'Manager', 'Employee']} />,
            children: [{ path: ROUTES.PERFORMANCE, element: <Performance /> }]
          },

          // TRAINING: Super Admin, HR Admin, HR Executive, Manager, Employee
          { 
            element: <ProtectedRoute roles={['Super Admin', 'HR Admin', 'HR Executive', 'Manager', 'Employee']} />,
            children: [{ path: ROUTES.TRAINING, element: <Training /> }]
          },

          // ASSETS: Super Admin, HR Admin, HR Executive, Manager, Employee
          { 
            element: <ProtectedRoute roles={['Super Admin', 'HR Admin', 'HR Executive', 'Manager', 'Employee']} />,
            children: [{ path: ROUTES.ASSETS, element: <Assets /> }]
          },

          // TIMESHEETS: Super Admin, HR Admin, Manager, Employee, Finance/Payroll Admin
          { 
            element: <ProtectedRoute roles={['Super Admin', 'HR Admin', 'Manager', 'Employee', 'Finance/Payroll Admin']} />,
            children: [{ path: ROUTES.TIMESHEETS, element: <Timesheets /> }]
          },

          // HELPDESK: all six roles
          { path: ROUTES.HELPDESK, element: <Helpdesk /> },

          // POLICIES: all six roles
          { path: ROUTES.POLICIES, element: <Policies /> },
        ],
      }
    ]
  },
]);
