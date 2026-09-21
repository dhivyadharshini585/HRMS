import { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { getEmployee, getMyProfile } from '../../services/employeeService';
import { useAuthContext } from '../../context/AuthContext';
import { ROUTES } from '../../constants/routes';

export default function EmployeeView() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { hasPermission } = useAuthContext();
  
  const [employee, setEmployee] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    const controller = new AbortController();
    
    const fetchProfileData = async () => {
      setLoading(true);
      setError('');
      try {
        let data;
        if (id) {
          data = await getEmployee(id, { signal: controller.signal });
        } else {
          data = await getMyProfile({ signal: controller.signal });
        }
        setEmployee(data);
      } catch (err) {
        if (err.name !== 'CanceledError') {
          console.error(err);
          setError(err.response?.data?.message || 'Failed to load employee profile.');
        }
      } finally {
        setLoading(false);
      }
    };
    
    fetchProfileData();
    return () => { controller.abort(); };
  }, [id]);

  if (loading) {
    return (
      <div style={{ padding: '3rem', textAlign: 'center', color: 'var(--text-secondary)' }}>
        Loading employee profile...
      </div>
    );
  }

  if (error) {
    return (
      <div className="employee-view-page">
        <div style={{ padding: '2rem', color: '#ef4444', backgroundColor: '#fee2e2', borderRadius: 'var(--radius-md)', marginBottom: '1rem' }}>
          {error}
        </div>
        {hasPermission('employees.view') && (
          <button className="btn-secondary" onClick={() => navigate(ROUTES.EMPLOYEES)}>
            Back to Employees
          </button>
        )}
      </div>
    );
  }

  if (!employee) {
    return (
      <div className="employee-view-page">
        <div style={{ padding: '2rem', textAlign: 'center', color: 'var(--text-secondary)' }}>
          Employee record not found.
        </div>
        {hasPermission('employees.view') && (
          <button className="btn-secondary" onClick={() => navigate(ROUTES.EMPLOYEES)}>
            Back to Employees
          </button>
        )}
      </div>
    );
  }

  const initials = `${employee.first_name?.charAt(0) || ''}${employee.last_name?.charAt(0) || ''}`;

  return (
    <div className="employee-view-page">
      {/* Top Bar Header */}
      <div className="page-header">
        <div>
          <h1 className="page-title">Employee Profile</h1>
          <p style={{ color: 'var(--text-secondary)', fontSize: '0.875rem' }}>
            Comprehensive employee details and history
          </p>
        </div>
        <div style={{ display: 'flex', gap: '0.5rem' }}>
          {hasPermission('employees.view') && (
            <button className="btn-secondary" onClick={() => navigate(ROUTES.EMPLOYEES)}>
              Back to Employees
            </button>
          )}
          {hasPermission('documents.view') && (
            <button className="btn-secondary" onClick={() => navigate(`${ROUTES.DOCUMENTS}?employee_id=${employee.id}`)}>
              📁 Documents
            </button>
          )}
          {hasPermission('employees.update') && (
            <button className="btn-primary" onClick={() => navigate(ROUTES.EMPLOYEE_EDIT.replace(':id', employee.id))}>
              Edit Profile
            </button>
          )}
        </div>
      </div>

      {/* Hero Header Card */}
      <div className="detail-card" style={{ marginBottom: '1.5rem' }}>
        <div className="detail-header" style={{ borderBottom: 'none', paddingBottom: 0, marginBottom: 0 }}>
          <div className="detail-avatar">
            {initials}
          </div>
          <div className="detail-info" style={{ flexGrow: 1 }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem', flexWrap: 'wrap' }}>
              <h2 style={{ fontSize: '1.5rem', fontWeight: 600 }}>{employee.first_name} {employee.last_name}</h2>
              <span className={`badge ${employee.employment_status?.toLowerCase().replace(' ', '-')}`}>
                {employee.employment_status}
              </span>
            </div>
            <p style={{ color: 'var(--text-secondary)', fontSize: '0.875rem', margin: '0.25rem 0' }}>
              {employee.employee_code} • {employee.designation?.title || 'No Designation'} ({employee.department?.name || 'No Department'})
            </p>
            <div style={{ display: 'flex', gap: '1rem', flexWrap: 'wrap', marginTop: '0.75rem', fontSize: '0.875rem', color: 'var(--text-secondary)' }}>
              <span>📧 {employee.email}</span>
              <span>📞 {employee.phone || 'N/A'}</span>
              <span>📍 {employee.work_location || 'Headquarters'}</span>
              <span>⏰ {employee.work_shift || 'Standard Day Shift'}</span>
            </div>
          </div>
        </div>
      </div>

      {/* 2-Column Section Grid */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(400px, 1fr))', gap: '1.5rem' }}>
        
        {/* Personal Details */}
        <div className="detail-card">
          <h3 style={{ fontSize: '1.125rem', fontWeight: 600, marginBottom: '1rem', color: 'var(--text-primary)', borderBottom: '1px solid var(--border-color)', paddingBottom: '0.5rem' }}>
            Personal Details
          </h3>
          <div className="detail-grid" style={{ gridTemplateColumns: '1fr 1fr' }}>
            <div className="detail-item">
              <span className="detail-label">Employee ID</span>
              <span className="detail-value">{employee.employee_code}</span>
            </div>
            <div className="detail-item">
              <span className="detail-label">First Name</span>
              <span className="detail-value">{employee.first_name}</span>
            </div>
            <div className="detail-item">
              <span className="detail-label">Last Name</span>
              <span className="detail-value">{employee.last_name}</span>
            </div>
            <div className="detail-item">
              <span className="detail-label">Date of Birth</span>
              <span className="detail-value">
                {employee.date_of_birth ? new Date(employee.date_of_birth).toLocaleDateString() : '-'}
              </span>
            </div>
            <div className="detail-item">
              <span className="detail-label">Gender</span>
              <span className="detail-value">{employee.gender || '-'}</span>
            </div>
            <div className="detail-item">
              <span className="detail-label">User Account</span>
              <span className="detail-value">{employee.user?.name ? `${employee.user.name} (${employee.user.email})` : 'Linked'}</span>
            </div>
          </div>
        </div>

        {/* Contact Details */}
        <div className="detail-card">
          <h3 style={{ fontSize: '1.125rem', fontWeight: 600, marginBottom: '1rem', color: 'var(--text-primary)', borderBottom: '1px solid var(--border-color)', paddingBottom: '0.5rem' }}>
            Contact Information
          </h3>
          <div className="detail-grid" style={{ gridTemplateColumns: '1fr 1fr' }}>
            <div className="detail-item">
              <span className="detail-label">Email Address</span>
              <span className="detail-value">{employee.email}</span>
            </div>
            <div className="detail-item">
              <span className="detail-label">Phone Number</span>
              <span className="detail-value">{employee.phone || '-'}</span>
            </div>
            <div className="detail-item" style={{ gridColumn: '1 / -1' }}>
              <span className="detail-label">Address</span>
              <span className="detail-value">
                {[employee.address, employee.city, employee.state, employee.country, employee.postal_code].filter(Boolean).join(', ') || '-'}
              </span>
            </div>
          </div>
        </div>

        {/* Emergency Contact */}
        <div className="detail-card">
          <h3 style={{ fontSize: '1.125rem', fontWeight: 600, marginBottom: '1rem', color: 'var(--text-primary)', borderBottom: '1px solid var(--border-color)', paddingBottom: '0.5rem' }}>
            Emergency Contact
          </h3>
          <div className="detail-grid" style={{ gridTemplateColumns: '1fr 1fr' }}>
            <div className="detail-item">
              <span className="detail-label">Contact Name</span>
              <span className="detail-value">{employee.emergency_contact_name || '-'}</span>
            </div>
            <div className="detail-item">
              <span className="detail-label">Relationship</span>
              <span className="detail-value">{employee.emergency_contact_relationship || '-'}</span>
            </div>
            <div className="detail-item" style={{ gridColumn: '1 / -1' }}>
              <span className="detail-label">Emergency Phone</span>
              <span className="detail-value">{employee.emergency_contact_phone || '-'}</span>
            </div>
          </div>
        </div>

        {/* Job & Employment Details */}
        <div className="detail-card">
          <h3 style={{ fontSize: '1.125rem', fontWeight: 600, marginBottom: '1rem', color: 'var(--text-primary)', borderBottom: '1px solid var(--border-color)', paddingBottom: '0.5rem' }}>
            Job & Employment Details
          </h3>
          <div className="detail-grid" style={{ gridTemplateColumns: '1fr 1fr' }}>
            <div className="detail-item">
              <span className="detail-label">Department</span>
              <span className="detail-value">{employee.department?.name || '-'}</span>
            </div>
            <div className="detail-item">
              <span className="detail-label">Designation</span>
              <span className="detail-value">{employee.designation?.title || '-'}</span>
            </div>
            <div className="detail-item">
              <span className="detail-label">Job Level</span>
              <span className="detail-value">{employee.job_level || 'Mid-Level'}</span>
            </div>
            <div className="detail-item">
              <span className="detail-label">Reporting Manager</span>
              <span className="detail-value">
                {employee.manager ? `${employee.manager.first_name} ${employee.manager.last_name} (${employee.manager.designation?.title || 'Manager'})` : 'No Direct Manager'}
              </span>
            </div>
            <div className="detail-item">
              <span className="detail-label">Date of Joining</span>
              <span className="detail-value">
                {employee.date_of_joining ? new Date(employee.date_of_joining).toLocaleDateString() : '-'}
              </span>
            </div>
            <div className="detail-item">
              <span className="detail-label">Employment Type</span>
              <span className="detail-value">{employee.employment_type}</span>
            </div>
            <div className="detail-item">
              <span className="detail-label">Work Location</span>
              <span className="detail-value">{employee.work_location || 'Headquarters'}</span>
            </div>
            <div className="detail-item">
              <span className="detail-label">Assigned Shift</span>
              <span className="detail-value">
                {employee.current_shift ? `${employee.current_shift.name} (${employee.current_shift.start_time?.substring(0, 5)} - ${employee.current_shift.end_time?.substring(0, 5)})` : employee.shift ? `${employee.shift.name} (${employee.shift.start_time?.substring(0, 5)} - ${employee.shift.end_time?.substring(0, 5)})` : (employee.work_shift || 'Standard Day Shift')}
              </span>
            </div>
          </div>
        </div>

      </div>

      {/* Employment History Section */}
      <div className="detail-card" style={{ marginTop: '1.5rem' }}>
        <h3 style={{ fontSize: '1.125rem', fontWeight: 600, marginBottom: '1rem', color: 'var(--text-primary)', borderBottom: '1px solid var(--border-color)', paddingBottom: '0.5rem' }}>
          Employment History
        </h3>
        {!employee.employment_histories || employee.employment_histories.length === 0 ? (
          <p style={{ color: 'var(--text-secondary)', fontSize: '0.875rem', fontStyle: 'italic', padding: '1rem 0' }}>
            No prior employment history records on file.
          </p>
        ) : (
          <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
            {employee.employment_histories.map(history => (
              <div key={history.id} style={{ padding: '1rem', backgroundColor: 'var(--bg-surface-hover)', borderRadius: 'var(--radius-md)', border: '1px solid var(--border-color)' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '0.25rem' }}>
                  <h4 style={{ fontWeight: 600, fontSize: '0.95rem' }}>{history.job_title} at {history.company_name}</h4>
                  <span style={{ fontSize: '0.75rem', color: 'var(--text-secondary)' }}>
                    {history.start_date ? new Date(history.start_date).toLocaleDateString() : 'N/A'} - {history.end_date ? new Date(history.end_date).toLocaleDateString() : 'Present'}
                  </span>
                </div>
                {history.responsibilities && (
                  <p style={{ fontSize: '0.85rem', color: 'var(--text-secondary)', margin: 0 }}>
                    {history.responsibilities}
                  </p>
                )}
              </div>
            ))}
          </div>
        )}
      </div>

    </div>
  );
}

