import React, { useEffect, useState } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import { fetchTickets } from '../../store/helpdeskSlice';
import { helpdeskService } from '../../services/helpdeskService';
import { IconSearch } from '../../components/common/Icons';

export default function TicketList() {
  const dispatch = useDispatch();
  const { items: tickets, loading } = useSelector(state => state.helpdesk);
  const [activeTab, setActiveTab] = useState('Open');
  const [searchTerm, setSearchTerm] = useState('');

  const tabs = ['Open', 'Assigned', 'In Progress', 'Waiting', 'Resolved', 'Closed'];

  useEffect(() => {
    dispatch(fetchTickets());
  }, [dispatch]);

  const handleStatusChange = async (id, newStatus) => {
    try {
      await helpdeskService.updateTicketStatus(id, newStatus);
      dispatch(fetchTickets()); // refresh list
    } catch (err) {
      console.error('Failed to update status', err);
    }
  };

  const filteredTickets = tickets.filter(t => 
    t.status === activeTab && 
    (t.ticket_number?.toLowerCase().includes(searchTerm.toLowerCase()) || 
     t.problem?.toLowerCase().includes(searchTerm.toLowerCase()))
  );

  return (
    <div className="detail-card">
      <div className="detail-card-header" style={{ flexWrap: 'wrap', gap: 'var(--space-4)' }}>
        <div>
          <h2 style={{ fontSize: '1.125rem', fontWeight: '600', color: 'var(--text-primary)', marginBottom: '4px' }}>
            IT Helpdesk
          </h2>
          <p style={{ fontSize: '0.875rem', color: 'var(--text-secondary)' }}>
            Manage and resolve internal IT support tickets.
          </p>
        </div>
        <div style={{ display: 'flex', gap: 'var(--space-2)' }}>
          <div className="search-bar" style={{ position: 'relative' }}>
            <IconSearch width="16" height="16" style={{ position: 'absolute', left: '10px', top: '10px', color: 'var(--text-muted)' }} />
            <input 
              type="text" 
              placeholder="Search tickets..." 
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              className="filter-input"
              style={{ paddingLeft: '32px' }}
            />
          </div>
          <button className="btn-primary">New Ticket</button>
        </div>
      </div>

      <div style={{ padding: '0 var(--space-6)', display: 'flex', gap: 'var(--space-4)', borderBottom: '1px solid var(--border-color)', overflowX: 'auto' }}>
        {tabs.map(tab => (
          <button
            key={tab}
            onClick={() => setActiveTab(tab)}
            style={{
              padding: 'var(--space-3) var(--space-1)',
              background: 'none',
              border: 'none',
              borderBottom: activeTab === tab ? '2px solid var(--primary-color)' : '2px solid transparent',
              color: activeTab === tab ? 'var(--primary-color)' : 'var(--text-secondary)',
              fontWeight: activeTab === tab ? '600' : '400',
              cursor: 'pointer',
              whiteSpace: 'nowrap'
            }}
          >
            {tab}
          </button>
        ))}
      </div>

      <div className="table-container" style={{ borderTop: 'none', borderRadius: '0 0 var(--radius-lg) var(--radius-lg)' }}>
        {loading ? (
          <div className="state-container"><p>Loading tickets...</p></div>
        ) : filteredTickets.length === 0 ? (
          <div className="state-container"><p>No {activeTab.toLowerCase()} tickets found.</p></div>
        ) : (
          <table className="data-table">
            <thead>
              <tr>
                <th>Ticket #</th>
                <th>Problem</th>
                <th>Category</th>
                <th>Priority</th>
                <th>Assigned To</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              {filteredTickets.map(ticket => (
                <tr key={ticket.id}>
                  <td style={{ fontWeight: '500', color: 'var(--primary-color)' }}>{ticket.ticket_number}</td>
                  <td>{ticket.problem}</td>
                  <td>{ticket.category}</td>
                  <td>
                    <span className={`badge badge-${ticket.priority === 'High' ? 'danger' : ticket.priority === 'Medium' ? 'warning' : 'info'}`}>
                      {ticket.priority}
                    </span>
                  </td>
                  <td>{ticket.assigned_to_name || 'Unassigned'}</td>
                  <td>
                    <select 
                      className="filter-select"
                      style={{ padding: '0.25rem 2rem 0.25rem 0.5rem', fontSize: '0.875rem' }}
                      value={ticket.status}
                      onChange={(e) => handleStatusChange(ticket.id, e.target.value)}
                    >
                      {tabs.map(statusOption => (
                        <option key={statusOption} value={statusOption}>{statusOption}</option>
                      ))}
                    </select>
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
