import React, { useState, useEffect } from 'react';
import { getSalaryStructures, createSalaryStructure, deleteSalaryStructure } from '../../services/payrollService';

export default function SalaryStructures() {
  const [structures, setStructures] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchStructures();
  }, []);

  const fetchStructures = async () => {
    try {
      const { data } = await getSalaryStructures();
      setStructures(data);
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  const handleDelete = async (id) => {
    try {
      await deleteSalaryStructure(id);
      fetchStructures();
    } catch (err) {
      alert('Failed to delete');
    }
  };

  if (loading) return <div>Loading Salary Structures...</div>;

  return (
    <div className="bg-white p-4 shadow rounded">
      <h2 className="text-xl font-bold mb-4">Salary Structures</h2>
      <table className="min-w-full table-auto">
        <thead>
          <tr className="bg-gray-200">
            <th className="py-2 px-4 text-left">Employee</th>
            <th className="py-2 px-4 text-left">Effective From</th>
            <th className="py-2 px-4 text-left">Status</th>
            <th className="py-2 px-4 text-left">Components</th>
            <th className="py-2 px-4 text-center">Actions</th>
          </tr>
        </thead>
        <tbody>
          {structures.map((s) => (
            <tr key={s.id} className="border-b">
              <td className="py-2 px-4">{s.employee?.first_name} {s.employee?.last_name}</td>
              <td className="py-2 px-4">{s.effective_from}</td>
              <td className="py-2 px-4">{s.status}</td>
              <td className="py-2 px-4">{s.components?.length} items</td>
              <td className="py-2 px-4 text-center">
                <button onClick={() => handleDelete(s.id)} className="bg-red-500 text-white px-2 py-1 rounded">Delete</button>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
