import React, { useEffect } from 'react';
import { Card, Table, Spinner, Alert } from 'react-bootstrap';
import { useDispatch, useSelector } from 'react-redux';
import { setEmployees, setLoading, setError } from '../../store/employeeSlice';
import { employeeService } from '../../services/employeeService';

const EmployeeList = () => {
  const dispatch = useDispatch();
  const { employees, loading, error } = useSelector((state) => state.employee);

  useEffect(() => {
    const fetchEmployees = async () => {
      dispatch(setLoading(true));
      try {
        const data = await employeeService.getAll();
        // Adjust depending on pagination structure from Laravel
        dispatch(setEmployees(data.data || data));
        dispatch(setError(null));
      } catch (err) {
        dispatch(setError('Failed to load employees.'));
      } finally {
        dispatch(setLoading(false));
      }
    };

    fetchEmployees();
  }, [dispatch]);

  return (
    <div>
      <div className="d-flex justify-content-between align-items-center mb-4">
        <h3>Employees</h3>
      </div>

      <Card className="border-0 shadow-sm">
        <Card.Body>
          {loading ? (
            <div className="text-center py-5">
              <Spinner animation="border" />
            </div>
          ) : error ? (
            <Alert variant="danger">{error}</Alert>
          ) : (
            <Table responsive hover>
              <thead>
                <tr>
                  <th>Employee ID</th>
                  <th>Name</th>
                  <th>Department</th>
                  <th>Designation</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                {employees.length === 0 ? (
                  <tr>
                    <td colSpan="5" className="text-center py-4">No employees found.</td>
                  </tr>
                ) : (
                  employees.map((emp) => (
                    <tr key={emp.id}>
                      <td>{emp.employee_id || `EMP-${emp.id}`}</td>
                      <td>{emp.first_name} {emp.last_name}</td>
                      <td>{emp.department?.name || 'N/A'}</td>
                      <td>{emp.designation?.name || 'N/A'}</td>
                      <td>
                        <span className={`badge bg-${emp.status === 'Active' ? 'success' : 'secondary'}`}>
                          {emp.status || 'Active'}
                        </span>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </Table>
          )}
        </Card.Body>
      </Card>
    </div>
  );
};

export default EmployeeList;
