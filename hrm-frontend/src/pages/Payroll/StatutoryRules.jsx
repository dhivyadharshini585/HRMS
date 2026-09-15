import React, { useState, useEffect } from 'react';
import api from '../../services/api';

export default function StatutoryRules() {
  const [rules, setRules] = useState([]);
  const [loading, setLoading] = useState(true);
  const [showModal, setShowModal] = useState(false);
  const [editingRule, setEditingRule] = useState(null);

  const [formData, setFormData] = useState({
    rule_name: '',
    rule_type: 'Percentage',
    base_component: 'Basic Salary',
    percentage: '',
    fixed_amount: '',
    slabs: [],
    effective_from: '',
    is_active: true
  });

  const fetchRules = async () => {
    try {
      const response = await api.get('/payroll/statutory-rules');
      setRules(response.data);
    } catch (error) {
      console.error('Error fetching rules:', error);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchRules();
  }, []);

  const handleInputChange = (e) => {
    const { name, value, type, checked } = e.target;
    setFormData(prev => ({
      ...prev,
      [name]: type === 'checkbox' ? checked : value
    }));
  };

  const handleAddSlab = () => {
    setFormData(prev => ({
      ...prev,
      slabs: [...prev.slabs, { min: '', max: '', amount: '' }]
    }));
  };

  const handleSlabChange = (index, field, value) => {
    const updatedSlabs = [...formData.slabs];
    // If it's max and the user leaves it blank or "null", allow open-ended slab
    updatedSlabs[index][field] = value === '' && field === 'max' ? null : value;
    setFormData(prev => ({
      ...prev,
      slabs: updatedSlabs
    }));
  };

  const handleRemoveSlab = (index) => {
    setFormData(prev => ({
      ...prev,
      slabs: prev.slabs.filter((_, i) => i !== index)
    }));
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    try {
      if (editingRule) {
        await api.put(`/payroll/statutory-rules/${editingRule.id}`, formData);
      } else {
        await api.post('/payroll/statutory-rules', formData);
      }
      setShowModal(false);
      fetchRules();
    } catch (error) {
      console.error('Error saving rule:', error);
      alert('Failed to save rule. Please check input values.');
    }
  };

  const openAddModal = () => {
    setEditingRule(null);
    setFormData({
      rule_name: '',
      rule_type: 'Percentage',
      base_component: 'Basic Salary',
      percentage: '',
      fixed_amount: '',
      slabs: [],
      effective_from: new Date().toISOString().split('T')[0],
      is_active: true
    });
    setShowModal(true);
  };

  const openEditModal = (rule) => {
    setEditingRule(rule);
    setFormData({
      rule_name: rule.rule_name,
      rule_type: rule.rule_type,
      base_component: rule.base_component || 'Basic Salary',
      percentage: rule.percentage || '',
      fixed_amount: rule.fixed_amount || '',
      slabs: rule.slabs || [],
      effective_from: rule.effective_from ? rule.effective_from.split('T')[0] : '',
      is_active: rule.is_active
    });
    setShowModal(true);
  };

  const toggleStatus = async (rule) => {
    try {
      if (rule.is_active) {
        await api.patch(`/payroll/statutory-rules/${rule.id}/deactivate`);
      } else {
        await api.patch(`/payroll/statutory-rules/${rule.id}/activate`);
      }
      fetchRules();
    } catch (error) {
      console.error('Error toggling status:', error);
    }
  };

  if (loading) {
    return <div className="p-6 text-center">Loading statutory rules...</div>;
  }

  return (
    <div className="p-6 max-w-7xl mx-auto">
      <div className="flex justify-between items-center mb-6">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">Statutory Payroll Rules</h1>
          <p className="text-slate-500 text-sm mt-1">Manage PF, ESI, PT, TDS, and other statutory deductions</p>
        </div>
        <button
          onClick={openAddModal}
          className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center font-medium shadow-sm transition-colors"
        >
          <span className="mr-1.5 text-lg leading-none">+</span>
          Add Rule
        </button>
      </div>

      <div className="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <table className="min-w-full divide-y divide-slate-200">
          <thead className="bg-slate-50">
            <tr>
              <th className="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Rule Name</th>
              <th className="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Type</th>
              <th className="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Value</th>
              <th className="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Effective From</th>
              <th className="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Status</th>
              <th className="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody className="bg-white divide-y divide-slate-200">
            {rules.map((rule) => (
              <tr key={rule.id} className="hover:bg-slate-50">
                <td className="px-6 py-4 whitespace-nowrap">
                  <div className="font-medium text-slate-900">{rule.rule_name}</div>
                  <div className="text-xs text-slate-500">Base: {rule.base_component || 'N/A'}</div>
                </td>
                <td className="px-6 py-4 whitespace-nowrap text-sm text-slate-500">
                  {rule.rule_type}
                </td>
                <td className="px-6 py-4 whitespace-nowrap text-sm text-slate-900">
                  {rule.rule_type === 'Percentage' ? `${rule.percentage}%` :
                   rule.rule_type === 'Fixed' ? `₹${rule.fixed_amount}` : 'Slab Based'}
                </td>
                <td className="px-6 py-4 whitespace-nowrap text-sm text-slate-500">
                  {new Date(rule.effective_from).toLocaleDateString()}
                </td>
                <td className="px-6 py-4 whitespace-nowrap">
                  <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${rule.is_active ? 'bg-green-100 text-green-800' : 'bg-slate-100 text-slate-800'}`}>
                    {rule.is_active ? 'Active' : 'Inactive'}
                  </span>
                </td>
                <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                  <div className="flex justify-end space-x-2">
                    <button
                      onClick={() => toggleStatus(rule)}
                      className={`p-1.5 rounded-md ${rule.is_active ? 'text-orange-600 hover:bg-orange-50' : 'text-green-600 hover:bg-green-50'}`}
                      title={rule.is_active ? 'Deactivate' : 'Activate'}
                    >
                      {rule.is_active ? <span className="font-bold text-lg leading-none">✕</span> : <span className="font-bold text-lg leading-none">✓</span>}
                    </button>
                    <button
                      onClick={() => openEditModal(rule)}
                      className="text-blue-600 hover:text-blue-900 hover:bg-blue-50 px-2 py-1 rounded-md text-sm font-medium"
                      title="Edit Rule"
                    >
                      Edit
                    </button>
                  </div>
                </td>
              </tr>
            ))}
            {rules.length === 0 && (
              <tr>
                <td colSpan="6" className="px-6 py-8 text-center text-slate-500">
                  No statutory rules configured.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      {showModal && (
        <div className="fixed inset-0 bg-slate-900/50 flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-xl shadow-xl max-w-md w-full max-h-[90vh] overflow-y-auto">
            <div className="px-6 py-4 border-b border-slate-200">
              <h2 className="text-xl font-semibold text-slate-900">
                {editingRule ? 'Edit Statutory Rule' : 'Add Statutory Rule'}
              </h2>
            </div>

            <form onSubmit={handleSubmit} className="p-6 space-y-4">
              <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">Rule Name (e.g. PF, ESI, PT)</label>
                <input
                  type="text"
                  name="rule_name"
                  required
                  value={formData.rule_name}
                  onChange={handleInputChange}
                  className="w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">Rule Type</label>
                <select
                  name="rule_type"
                  value={formData.rule_type}
                  onChange={handleInputChange}
                  className="w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                >
                  <option value="Percentage">Percentage</option>
                  <option value="Fixed">Fixed Amount</option>
                  <option value="Slab">Slab Based</option>
                </select>
              </div>

              {formData.rule_type === 'Percentage' && (
                <>
                  <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1">Base Component</label>
                    <select
                      name="base_component"
                      value={formData.base_component}
                      onChange={handleInputChange}
                      className="w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                    >
                      <option value="Basic Salary">Basic Salary</option>
                      <option value="Gross Earnings">Gross Earnings</option>
                    </select>
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1">Percentage (%)</label>
                    <input
                      type="number"
                      name="percentage"
                      step="0.01"
                      required
                      value={formData.percentage}
                      onChange={handleInputChange}
                      className="w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                    />
                  </div>
                </>
              )}

              {formData.rule_type === 'Fixed' && (
                <div>
                  <label className="block text-sm font-medium text-slate-700 mb-1">Fixed Amount (₹)</label>
                  <input
                    type="number"
                    name="fixed_amount"
                    step="0.01"
                    required
                    value={formData.fixed_amount}
                    onChange={handleInputChange}
                    className="w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                  />
                </div>
              )}

              {formData.rule_type === 'Slab' && (
                <div className="space-y-3">
                  <div className="flex justify-between items-center">
                    <label className="block text-sm font-medium text-slate-700">Slab Configuration</label>
                    <button
                      type="button"
                      onClick={handleAddSlab}
                      className="text-sm text-blue-600 hover:text-blue-800 font-medium"
                    >
                      + Add Slab
                    </button>
                  </div>

                  {formData.slabs.map((slab, index) => (
                    <div key={index} className="flex space-x-2 items-center bg-slate-50 p-2 rounded border border-slate-200">
                      <div className="flex-1">
                        <input
                          type="number"
                          placeholder="Min (₹)"
                          step="0.01"
                          required
                          value={slab.min !== null ? slab.min : ''}
                          onChange={(e) => handleSlabChange(index, 'min', e.target.value)}
                          className="w-full px-2 py-1.5 text-sm border border-slate-300 rounded shadow-sm"
                        />
                      </div>
                      <div className="text-slate-400">-</div>
                      <div className="flex-1">
                        <input
                          type="number"
                          placeholder="Max (Leave empty for infinity)"
                          step="0.01"
                          value={slab.max !== null ? slab.max : ''}
                          onChange={(e) => handleSlabChange(index, 'max', e.target.value)}
                          className="w-full px-2 py-1.5 text-sm border border-slate-300 rounded shadow-sm"
                        />
                      </div>
                      <div className="text-slate-400">=</div>
                      <div className="flex-1">
                        <input
                          type="number"
                          placeholder="Amount (₹)"
                          step="0.01"
                          required
                          value={slab.amount !== null ? slab.amount : ''}
                          onChange={(e) => handleSlabChange(index, 'amount', e.target.value)}
                          className="w-full px-2 py-1.5 text-sm border border-slate-300 rounded shadow-sm"
                        />
                      </div>
                      <button
                        type="button"
                        onClick={() => handleRemoveSlab(index)}
                        className="text-red-500 hover:text-red-700 p-1 px-2 font-bold text-lg leading-none"
                        title="Remove Slab"
                      >
                        ✕
                      </button>
                    </div>
                  ))}

                  {formData.slabs.length === 0 && (
                    <div className="text-sm text-slate-500 italic">No slabs added yet. Click "+ Add Slab" to begin.</div>
                  )}
                </div>
              )}

              <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">Effective From</label>
                <input
                  type="date"
                  name="effective_from"
                  required
                  value={formData.effective_from}
                  onChange={handleInputChange}
                  className="w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                />
              </div>

              <div className="flex items-center pt-2">
                <input
                  type="checkbox"
                  name="is_active"
                  id="is_active"
                  checked={formData.is_active}
                  onChange={handleInputChange}
                  className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-slate-300 rounded"
                />
                <label htmlFor="is_active" className="ml-2 block text-sm text-slate-900">
                  Active immediately
                </label>
              </div>

              <div className="pt-4 flex justify-end space-x-3">
                <button
                  type="button"
                  onClick={() => setShowModal(false)}
                  className="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                >
                  {editingRule ? 'Update Rule' : 'Save Rule'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
