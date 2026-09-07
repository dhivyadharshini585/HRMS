import React, { useState } from 'react';
import { createPayroll } from '../../services/payrollService';

export default function PayrollProcessing({ onProcessed }) {
  const [employeeId, setEmployeeId] = useState('');
  const [month, setMonth] = useState('');
  const [year, setYear] = useState('');
  const [error, setError] = useState('');

  const handleProcess = async (e) => {
    e.preventDefault();
    try {
      await createPayroll({ employee_id: employeeId, month, year });
      alert('Payroll processed successfully');
      if (onProcessed) onProcessed();
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to process payroll');
    }
  };

  return (
    <div className="bg-white p-4 shadow rounded mt-4">
      <h2 className="text-xl font-bold mb-4">Process Payroll</h2>
      {error && <div className="text-red-500 mb-2">{error}</div>}
      <form onSubmit={handleProcess} className="flex gap-4">
        <input type="text" placeholder="Employee ID" value={employeeId} onChange={e => setEmployeeId(e.target.value)} className="border p-2 rounded" required />
        <input type="number" placeholder="Month (1-12)" min="1" max="12" value={month} onChange={e => setMonth(e.target.value)} className="border p-2 rounded" required />
        <input type="number" placeholder="Year" min="2000" max="2100" value={year} onChange={e => setYear(e.target.value)} className="border p-2 rounded" required />
        <button type="submit" className="bg-blue-600 text-white px-4 py-2 rounded">Process</button>
      </form>
    </div>
  );
}
