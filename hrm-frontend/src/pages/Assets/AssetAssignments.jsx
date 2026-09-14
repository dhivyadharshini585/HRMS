import React, { useEffect, useState } from 'react';
import { assetService } from '../../services/assetService';

export default function AssetAssignments() {
  const [assignments, setAssignments] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchAssignments();
  }, []);

  const fetchAssignments = async () => {
    try {
      const data = await assetService.getAssignments(); 
      // Filter only active assignments
      setAssignments(data.filter(a => a.status === 'Active') || []);
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  const handleReturnAsset = async (assetId) => {
    if (!window.confirm('Confirm return of this asset?')) return;
    try {
      const payload = {
        returned_date: new Date().toISOString().split('T')[0],
        condition_at_return: 'Good' // Default to Good for now, ideally selected from UI
      };
      await assetService.returnAsset(assetId, payload);
      fetchAssignments();
    } catch (err) {
      alert('Failed to return asset');
    }
  };

  return (
    <div className="detail-card">
      <div className="detail-card-header">
        <div>
          <h2 style={{ fontSize: '1.125rem', fontWeight: '600', color: 'var(--text-primary)' }}>Asset Assignments</h2>
          <p style={{ fontSize: '0.875rem', color: 'var(--text-secondary)' }}>Track who has which assets currently issued.</p>
        </div>
      </div>
      <div className="table-container">
        {loading ? (
          <div className="state-container"><p>Loading assignments...</p></div>
        ) : assignments.length === 0 ? (
          <div className="state-container"><p>No assets currently assigned.</p></div>
        ) : (
          <table className="data-table">
            <thead>
              <tr>
                <th>Asset ID</th>
                <th>Asset Name</th>
                <th>Assigned To (Employee)</th>
                <th>Issue Date</th>
                <th style={{ textAlign: 'right' }}>Actions</th>
              </tr>
            </thead>
            <tbody>
              {assignments.map(a => (
                <tr key={a.id}>
                  <td style={{ fontWeight: '500' }}>{a.asset?.asset_id}</td>
                  <td>{a.asset?.name}</td>
                  <td>{a.employee ? `${a.employee.first_name} ${a.employee.last_name}` : 'Unknown'}</td>
                  <td>{a.assigned_date || 'N/A'}</td>
                  <td style={{ textAlign: 'right' }}>
                    <button onClick={() => handleReturnAsset(a.asset_id)} className="btn-secondary" style={{ padding: '0.35rem 0.75rem', fontSize: '0.75rem' }}>
                      Mark Returned
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </div>
  );
}
