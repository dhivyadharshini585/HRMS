import { useState, useEffect } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { getEmployee, createEmployee, updateEmployee, getDepartments, getDesignations, getEmployees } from '../../services/employeeService';
import { ROUTES } from '../../constants/routes';

export default function EmployeeForm() {
  const navigate = useNavigate();
  const { id } = useParams();
  const isEdit = Boolean(id);
  
  const [loading, setLoading] = useState(isEdit);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState('');
  
  const [departments, setDepartments] = useState([]);
  const [designations, setDesignations] = useState([]);
  const [managerOptions, setManagerOptions] = useState([]);
  
  const [formData, setFormData] = useState({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    date_of_birth: '',
    gender: '',
    date_of_joining: '',
    department_id: '',
    designation_id: '',
    job_level: 'Mid-Level',
    manager_id: '',
    employment_type: 'Full-time',
    employment_status: 'Active',
    work_location: 'Headquarters',
    work_shift: 'Day Shift (9 AM - 5 PM)',
    address: '',
    city: '',
    state: '',
    country: '',
    postal_code: '',
    emergency_contact_name: '',
    emergency_contact_relationship: '',
    emergency_contact_phone: ''
  });

  useEffect(() => {
    const controller = new AbortController();
    
    const fetchInitData = async () => {
      try {
        const [depts, desigs, empRes] = await Promise.all([
          getDepartments({ signal: controller.signal }), 
          getDesignations({ signal: controller.signal }),
          getEmployees({ per_page: 100 }, { signal: controller.signal })
        ]);
        setDepartments(depts);
        setDesignations(desigs);
        
        // Filter out self from manager choices if editing
        const allEmps = empRes?.data || empRes || [];
        setManagerOptions(allEmps.filter(e => String(e.id) !== String(id)));

        if (isEdit) {
          const emp = await getEmployee(id, { signal: controller.signal });
          setFormData({
            first_name: emp.first_name || '',
            last_name: emp.last_name || '',
            email: emp.email || '',
            phone: emp.phone || '',
            date_of_birth: emp.date_of_birth || '',
            gender: emp.gender || '',
            date_of_joining: emp.date_of_joining || '',
            department_id: emp.department_id || '',
            designation_id: emp.designation_id || '',
            job_level: emp.job_level || 'Mid-Level',
            manager_id: emp.manager_id || '',
            employment_type: emp.employment_type || 'Full-time',
            employment_status: emp.employment_status || 'Active',
            work_location: emp.work_location || 'Headquarters',
            work_shift: emp.work_shift || 'Day Shift (9 AM - 5 PM)',
            address: emp.address || '',
            city: emp.city || '',
            state: emp.state || '',
            country: emp.country || '',
            postal_code: emp.postal_code || '',
            emergency_contact_name: emp.emergency_contact_name || '',
            emergency_contact_relationship: emp.emergency_contact_relationship || '',
            emergency_contact_phone: emp.emergency_contact_phone || ''
          });
        }
      } catch (err) {
        if (err.name !== 'CanceledError') {
          console.error(err);
          setError('Failed to load form data.');
        }
      } finally {
        setLoading(false);
      }
    };
    
    fetchInitData();
    return () => { controller.abort(); };
  }, [id, isEdit]);

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData(prev => ({ ...prev, [name]: value }));
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setSubmitting(true);
    setError('');
    
    try {
      if (isEdit) {
        await updateEmployee(id, formData);
      } else {
        await createEmployee(formData);
      }
      navigate(ROUTES.EMPLOYEES);
    } catch (err) {
      if (err.response?.data?.errors) {
        const firstError = Object.values(err.response.data.errors)[0][0];
        setError(firstError);
      } else {
        setError('An unexpected error occurred while saving.');
      }
    } finally {
      setSubmitting(false);
    }
  };

  if (loading) return <div style={{ padding: '2rem', textAlign: 'center' }}>Loading employee form...</div>;

  return (
    <div className="employee-form-page">
      <div className="page-header">
        <h1 className="page-title">{isEdit ? 'Edit Employee Profile' : 'Add New Employee'}</h1>
      </div>
      
      {error && <div style={{ color: '#ef4444', backgroundColor: '#fee2e2', padding: '0.75rem', borderRadius: 'var(--radius-md)', marginBottom: '1rem' }}>{error}</div>}

      <div className="form-container">
        <form onSubmit={handleSubmit}>
          
          {/* Section: Basic Info */}
          <h3 style={{ fontSize: '1rem', fontWeight: 600, marginBottom: '1rem', borderBottom: '1px solid var(--border-color)', paddingBottom: '0.5rem' }}>Basic Information</h3>
          <div className="form-grid">
            <div className="form-group">
              <label className="form-label">First Name *</label>
              <input type="text" name="first_name" className="form-input" value={formData.first_name} onChange={handleChange} required />
            </div>
            <div className="form-group">
              <label className="form-label">Last Name *</label>
              <input type="text" name="last_name" className="form-input" value={formData.last_name} onChange={handleChange} required />
            </div>
            
            <div className="form-group">
              <label className="form-label">Email Address *</label>
              <input type="email" name="email" className="form-input" value={formData.email} onChange={handleChange} required />
            </div>
            <div className="form-group">
              <label className="form-label">Phone Number</label>
              <input type="text" name="phone" className="form-input" value={formData.phone} onChange={handleChange} />
            </div>
            
            <div className="form-group">
              <label className="form-label">Date of Birth</label>
              <input type="date" name="date_of_birth" className="form-input" value={formData.date_of_birth} onChange={handleChange} />
            </div>
            <div className="form-group">
              <label className="form-label">Gender</label>
              <select name="gender" className="form-select" value={formData.gender} onChange={handleChange}>
                <option value="">Select Gender</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
                <option value="Other">Other</option>
              </select>
            </div>
          </div>

          {/* Section: Job & Employment */}
          <h3 style={{ fontSize: '1rem', fontWeight: 600, margin: '1.5rem 0 1rem 0', borderBottom: '1px solid var(--border-color)', paddingBottom: '0.5rem' }}>Job & Employment Details</h3>
          <div className="form-grid">
            <div className="form-group">
              <label className="form-label">Date of Joining *</label>
              <input type="date" name="date_of_joining" className="form-input" value={formData.date_of_joining} onChange={handleChange} required />
            </div>
            
            <div className="form-group">
              <label className="form-label">Department</label>
              <select name="department_id" className="form-select" value={formData.department_id} onChange={handleChange}>
                <option value="">Select Department</option>
                {departments.map(d => <option key={d.id} value={d.id}>{d.name}</option>)}
              </select>
            </div>
            <div className="form-group">
              <label className="form-label">Designation</label>
              <select name="designation_id" className="form-select" value={formData.designation_id} onChange={handleChange}>
                <option value="">Select Designation</option>
                {designations.map(d => <option key={d.id} value={d.id}>{d.title}</option>)}
              </select>
            </div>

            <div className="form-group">
              <label className="form-label">Job Level</label>
              <select name="job_level" className="form-select" value={formData.job_level} onChange={handleChange}>
                <option value="Junior">Junior</option>
                <option value="Mid-Level">Mid-Level</option>
                <option value="Senior">Senior</option>
                <option value="Lead">Lead</option>
                <option value="Executive">Executive</option>
              </select>
            </div>

            <div className="form-group">
              <label className="form-label">Reporting Manager</label>
              <select name="manager_id" className="form-select" value={formData.manager_id} onChange={handleChange}>
                <option value="">No Manager (Top Level)</option>
                {managerOptions.map(m => (
                  <option key={m.id} value={m.id}>
                    {m.first_name} {m.last_name} ({m.employee_code})
                  </option>
                ))}
              </select>
            </div>

            <div className="form-group">
              <label className="form-label">Employment Type</label>
              <select name="employment_type" className="form-select" value={formData.employment_type} onChange={handleChange}>
                <option value="Full-time">Full-time</option>
                <option value="Part-time">Part-time</option>
                <option value="Contract">Contract</option>
                <option value="Intern">Intern</option>
              </select>
            </div>

            <div className="form-group">
              <label className="form-label">Work Location</label>
              <input type="text" name="work_location" className="form-input" placeholder="e.g. Headquarters, Remote" value={formData.work_location} onChange={handleChange} />
            </div>

            <div className="form-group">
              <label className="form-label">Work Shift</label>
              <input type="text" name="work_shift" className="form-input" placeholder="e.g. Day Shift (9 AM - 5 PM)" value={formData.work_shift} onChange={handleChange} />
            </div>
            
            {isEdit && (
              <div className="form-group">
                <label className="form-label">Employment Status</label>
                <select name="employment_status" className="form-select" value={formData.employment_status} onChange={handleChange}>
                  <option value="Active">Active</option>
                  <option value="Inactive">Inactive</option>
                  <option value="Probation">Probation</option>
                  <option value="On Leave">On Leave</option>
                  <option value="Terminated">Terminated</option>
                </select>
              </div>
            )}
          </div>

          {/* Section: Address */}
          <h3 style={{ fontSize: '1rem', fontWeight: 600, margin: '1.5rem 0 1rem 0', borderBottom: '1px solid var(--border-color)', paddingBottom: '0.5rem' }}>Address Information</h3>
          <div className="form-grid">
            <div className="form-group full-width">
              <label className="form-label">Street Address</label>
              <input type="text" name="address" className="form-input" value={formData.address} onChange={handleChange} />
            </div>
            
            <div className="form-group">
              <label className="form-label">City</label>
              <input type="text" name="city" className="form-input" value={formData.city} onChange={handleChange} />
            </div>
            <div className="form-group">
              <label className="form-label">State / Province</label>
              <input type="text" name="state" className="form-input" value={formData.state} onChange={handleChange} />
            </div>
            <div className="form-group">
              <label className="form-label">Country</label>
              <input type="text" name="country" className="form-input" value={formData.country} onChange={handleChange} />
            </div>
            <div className="form-group">
              <label className="form-label">Postal Code</label>
              <input type="text" name="postal_code" className="form-input" value={formData.postal_code} onChange={handleChange} />
            </div>
          </div>

          {/* Section: Emergency Contact */}
          <h3 style={{ fontSize: '1rem', fontWeight: 600, margin: '1.5rem 0 1rem 0', borderBottom: '1px solid var(--border-color)', paddingBottom: '0.5rem' }}>Emergency Contact</h3>
          <div className="form-grid">
            <div className="form-group">
              <label className="form-label">Emergency Contact Name</label>
              <input type="text" name="emergency_contact_name" className="form-input" value={formData.emergency_contact_name} onChange={handleChange} />
            </div>
            <div className="form-group">
              <label className="form-label">Relationship</label>
              <input type="text" name="emergency_contact_relationship" className="form-input" placeholder="e.g. Spouse, Parent, Sibling" value={formData.emergency_contact_relationship} onChange={handleChange} />
            </div>
            <div className="form-group full-width">
              <label className="form-label">Emergency Contact Phone</label>
              <input type="text" name="emergency_contact_phone" className="form-input" value={formData.emergency_contact_phone} onChange={handleChange} />
            </div>
          </div>
          
          <div className="form-actions">
            <button type="button" className="btn-secondary" onClick={() => navigate(ROUTES.EMPLOYEES)}>Cancel</button>
            <button type="submit" className="btn-primary" disabled={submitting}>
              {submitting ? 'Saving Profile...' : 'Save Employee Profile'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
