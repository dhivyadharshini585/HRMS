import React, { useState, useEffect } from 'react';
import { getPayrolls, approvePayroll, generatePayslip } from '../../services/payrollService';
import SalaryStructures from './SalaryStructures';
import PayrollProcessing from './PayrollProcessing';

function Payroll() {
  const [payrolls, setPayrolls] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [activeTab, setActiveTab] = useState('dashboard');

  useEffect(() => {
    fetchPayrolls();
  }, []);

  const fetchPayrolls = async () => {
    try {
      const { data } = await getPayrolls();
      setPayrolls(data);
    } catch (err) {
      setError('Failed to fetch payrolls');
    } finally {
      setLoading(false);
    }
  };

  const handleApprove = async (id) => {
    try {
      await approvePayroll(id);
      fetchPayrolls();
    } catch (err) {
      alert('Failed to approve payroll');
    }
  };

  const handleGeneratePayslip = async (id) => {
    try {
      await generatePayslip(id);
      fetchPayrolls();
      alert('Payslip generated successfully');
    } catch (err) {
      alert('Failed to generate payslip');
    }
  };

  if (loading) return <div>Loading payrolls...</div>;
  if (error) return <div>{error}</div>;

  return (
    <div className="p-6">
      <h1 className="text-2xl font-bold mb-4">Payroll Management</h1>
      
      <div className="flex gap-4 mb-4 border-b pb-2">
        <button onClick={() => setActiveTab('dashboard')} className={activeTab === 'dashboard' ? 'font-bold border-b-2 border-blue-500' : ''}>Dashboard</button>
        <button onClick={() => setActiveTab('structures')} className={activeTab === 'structures' ? 'font-bold border-b-2 border-blue-500' : ''}>Salary Structures</button>
      </div>

      {activeTab === 'structures' && <SalaryStructures />}

      {activeTab === 'dashboard' && (
        <>
          <PayrollProcessing onProcessed={fetchPayrolls} />
          
          <div className="bg-white shadow-md rounded my-6">
            <table className="min-w-full table-auto">
              <thead>
                <tr className="bg-gray-200 text-gray-600 uppercase text-sm leading-normal">
                  <th className="py-3 px-6 text-left">Employee</th>
                  <th className="py-3 px-6 text-left">Period</th>
                  <th className="py-3 px-6 text-left">Gross</th>
                  <th className="py-3 px-6 text-left">Net</th>
                  <th className="py-3 px-6 text-left">Status</th>
                  <th className="py-3 px-6 text-center">Actions</th>
                </tr>
              </thead>
              <tbody className="text-gray-600 text-sm font-light">
                {payrolls.map((payroll) => (
                  <tr key={payroll.id} className="border-b border-gray-200 hover:bg-gray-100">
                    <td className="py-3 px-6 text-left whitespace-nowrap">
                      {payroll.employee?.first_name} {payroll.employee?.last_name}
                    </td>
                    <td className="py-3 px-6 text-left">
                      {payroll.month}/{payroll.year}
                    </td>
                    <td className="py-3 px-6 text-left">{payroll.gross_earnings}</td>
                    <td className="py-3 px-6 text-left">{payroll.net_salary}</td>
                    <td className="py-3 px-6 text-left">
                      <span className={`py-1 px-3 rounded-full text-xs ${payroll.status === 'Approved' ? 'bg-green-200 text-green-600' : 'bg-yellow-200 text-yellow-600'}`}>
                        {payroll.status}
                      </span>
                    </td>
                    <td className="py-3 px-6 text-center">
                      {payroll.status !== 'Approved' && (
                        <button onClick={() => handleApprove(payroll.id)} className="bg-blue-500 text-white px-3 py-1 rounded mr-2">
                          Approve
                        </button>
                      )}
                      {payroll.status === 'Approved' && !payroll.payslip && (
                        <button onClick={() => handleGeneratePayslip(payroll.id)} className="bg-green-500 text-white px-3 py-1 rounded">
                          Generate Payslip
                        </button>
                      )}
                      {payroll.payslip && (
                        <span className="text-gray-500 text-xs italic">Payslip Generated</span>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </>
      )}
    </div>
  );
}

export default Payroll;
