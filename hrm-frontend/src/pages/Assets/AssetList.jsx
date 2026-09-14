import React, { useEffect, useState } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import { fetchAssets } from '../../store/assetSlice';

export default function AssetList() {
  const dispatch = useDispatch();
  const { items: assets, loading } = useSelector(state => state.assets);
  const [searchTerm, setSearchTerm] = useState('');

  useEffect(() => {
    dispatch(fetchAssets());
  }, [dispatch]);

  const filteredAssets = assets.filter(a => 
    a.asset_id?.toLowerCase().includes(searchTerm.toLowerCase()) || 
    a.name?.toLowerCase().includes(searchTerm.toLowerCase())
  );

  return (
    <div className="detail-card">
      <div className="detail-card-header">
        <div>
          <h2 style={{ fontSize: '1.125rem', fontWeight: '600', color: 'var(--text-primary)' }}>Company Assets</h2>
          <p style={{ fontSize: '0.875rem', color: 'var(--text-secondary)' }}>Manage laptops, monitors, and software licenses.</p>
        </div>
        <div style={{ display: 'flex', gap: 'var(--space-2)' }}>
          <input 
            type="text" 
            placeholder="Search assets..." 
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            className="filter-input"
          />
          <button className="btn-primary">Add Asset</button>
        </div>
      </div>
      <div className="table-container">
        {loading ? (
          <div className="state-container"><p>Loading assets...</p></div>
        ) : filteredAssets.length === 0 ? (
          <div className="state-container"><p>No assets found.</p></div>
        ) : (
          <table className="data-table">
            <thead>
              <tr>
                <th>Asset ID</th>
                <th>Name/Model</th>
                <th>Category</th>
                <th>Condition</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              {filteredAssets.map(asset => (
                <tr key={asset.id}>
                  <td style={{ fontWeight: '500' }}>{asset.asset_id}</td>
                  <td>{asset.name}</td>
                  <td>{asset.category}</td>
                  <td>{asset.condition}</td>
                  <td>
                    <span className={`badge badge-${asset.status === 'Assigned' ? 'info' : asset.status === 'Available' ? 'success' : 'warning'}`}>
                      {asset.status}
                    </span>
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
