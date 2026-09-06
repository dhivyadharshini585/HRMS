import React, { useState, useEffect, useCallback } from 'react';
import offerLetterService from '../../services/offerLetterService';
import candidateService from '../../services/candidateService';
import jobOpeningService from '../../services/jobOpeningService';
import departmentService from '../../services/departmentService';
import { useAuthContext } from '../../context/AuthContext';

const OFFER_STATUSES = ['Draft', 'Sent', 'Accepted', 'Rejected', 'Withdrawn', 'Expired'];
const EMPLOYMENT_TYPES = ['Full-time', 'Part-time', 'Contract', 'Internship'];
const SALARY_PERIODS = ['Annual', 'Monthly', 'Hourly'];

const initialFormData = {
  candidate_id: '',
  job_opening_id: '',
  department_id: '',
  position_title: '',
  employment_type: 'Full-time',
  base_salary: '',
  currency: 'USD',
  salary_period: 'Annual',
  offer_date: new Date().toISOString().split('T')[0],
  joining_date: '',
  expiry_date: '',
  terms_and_conditions: 'This offer is contingent upon successful verification of professional credentials and background check. Standard company policies and benefits apply.',
  special_allowances: '',
};

const OfferLetters = () => {
  const { user } = useAuthContext();

  const [offerLetters, setOfferLetters] = useState([]);
  const [candidates, setCandidates] = useState([]);
  const [jobOpenings, setJobOpenings] = useState([]);
  const [departments, setDepartments] = useState([]);
  const [pagination, setPagination] = useState({
    currentPage: 1,
    lastPage: 1,
    total: 0,
    perPage: 10,
  });

  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState('');
  const [successMessage, setSuccessMessage] = useState('');

  // Filters
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [candidateFilter, setCandidateFilter] = useState('');
  const [jobFilter, setJobFilter] = useState('');
  const [fromDateFilter, setFromDateFilter] = useState('');
  const [toDateFilter, setToDateFilter] = useState('');

  // Modals
  const [isFormModalOpen, setIsFormModalOpen] = useState(false);
  const [isDetailModalOpen, setIsDetailModalOpen] = useState(false);
  const [isSendModalOpen, setIsSendModalOpen] = useState(false);
  const [isRespondModalOpen, setIsRespondModalOpen] = useState(false);
  const [respondAction, setRespondAction] = useState(''); // 'accept', 'reject', 'withdraw'
  const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);

  const [activeOffer, setActiveOffer] = useState(null);
  const [formData, setFormData] = useState(initialFormData);
  const [formErrors, setFormErrors] = useState({});
  const [remarks, setRemarks] = useState('');
  const [remarksError, setRemarksError] = useState('');
  const [actionLoading, setActionLoading] = useState(false);
  const [downloadingId, setDownloadingId] = useState(null);

  // RBAC checks
  const canCreate = user?.roles?.some(r => ['Super Admin', 'HR Admin', 'HR Executive'].includes(r)) ||
                    user?.permissions?.includes('recruitment.offer_letters.create');
  const canUpdate = user?.roles?.some(r => ['Super Admin', 'HR Admin', 'HR Executive'].includes(r)) ||
                    user?.permissions?.includes('recruitment.offer_letters.update');
  const canDelete = user?.roles?.some(r => ['Super Admin', 'HR Admin'].includes(r)) ||
                    user?.permissions?.includes('recruitment.offer_letters.delete');
  const canSend = user?.roles?.some(r => ['Super Admin', 'HR Admin', 'HR Executive'].includes(r)) ||
                  user?.permissions?.includes('recruitment.offer_letters.send');
  const canRespond = user?.roles?.some(r => ['Super Admin', 'HR Admin', 'HR Executive'].includes(r)) ||
                     user?.permissions?.includes('recruitment.offer_letters.respond');
  const canDownload = user?.roles?.some(r => ['Super Admin', 'HR Admin', 'HR Executive'].includes(r)) ||
                      user?.permissions?.includes('recruitment.offer_letters.download') ||
                      user?.permissions?.includes('recruitment.offer_letters.view');

  // Fetch Offer Letters
  const fetchOfferLetters = useCallback(async (page = 1) => {
    setLoading(true);
    setError('');
    try {
      const params = {
        page,
        per_page: pagination.perPage,
      };
      if (search.trim()) params.search = search.trim();
      if (statusFilter) params.offer_status = statusFilter;
      if (candidateFilter) params.candidate_id = candidateFilter;
      if (jobFilter) params.job_opening_id = jobFilter;
      if (fromDateFilter) params.offer_date_from = fromDateFilter;
      if (toDateFilter) params.offer_date_to = toDateFilter;

      const response = await offerLetterService.getOfferLetters(params);
      const data = response?.data || response;
      setOfferLetters(data?.data || []);
      setPagination({
        currentPage: data?.current_page || 1,
        lastPage: data?.last_page || 1,
        total: data?.total || 0,
        perPage: data?.per_page || 10,
      });
    } catch (err) {
      console.error('Error fetching offer letters:', err);
      setError(err?.response?.data?.message || 'Failed to load offer letters.');
    } finally {
      setLoading(false);
    }
  }, [pagination.perPage, search, statusFilter, candidateFilter, jobFilter, fromDateFilter, toDateFilter]);

  // Fetch Candidates (Only Hired Candidates for create offer)
  const fetchCandidates = useCallback(async () => {
    try {
      const res = await candidateService.getCandidates({ per_page: 200 });
      const candList = res?.data?.data || res?.data || [];
      setCandidates(candList);
    } catch (err) {
      console.error('Error fetching candidates:', err);
    }
  }, []);

  // Fetch Job Openings
  const fetchJobOpenings = useCallback(async () => {
    try {
      const res = await jobOpeningService.getJobOpenings({ per_page: 200 });
      const list = res?.data?.data || res?.data || [];
      setJobOpenings(list);
    } catch (err) {
      console.error('Error fetching job openings:', err);
    }
  }, []);

  // Fetch Departments
  const fetchDepartments = useCallback(async () => {
    try {
      const res = await departmentService.getDepartments({ per_page: 100 });
      const list = res?.data?.data || res?.data || [];
      setDepartments(list);
    } catch (err) {
      console.error('Error fetching departments:', err);
    }
  }, []);

  useEffect(() => {
    fetchOfferLetters(1);
    fetchCandidates();
    fetchJobOpenings();
    fetchDepartments();
  }, [fetchOfferLetters, fetchCandidates, fetchJobOpenings, fetchDepartments]);

  // Handle Candidate Selection in Create Form
  const handleCandidateChange = (e) => {
    const candId = e.target.value;
    const selected = candidates.find(c => String(c.id) === String(candId));

    if (selected) {
      setFormData(prev => ({
        ...prev,
        candidate_id: candId,
        job_opening_id: selected.job_opening_id || '',
        department_id: selected.job_opening?.department_id || selected.job_opening?.department?.id || '',
        position_title: selected.job_opening?.title || prev.position_title,
      }));
    } else {
      setFormData(prev => ({
        ...prev,
        candidate_id: candId,
        job_opening_id: '',
        department_id: '',
      }));
    }
  };

  // Open Create Modal
  const openCreateModal = () => {
    setActiveOffer(null);
    setFormData(initialFormData);
    setFormErrors({});
    setError('');
    setIsFormModalOpen(true);
  };

  // Open Edit Modal
  const openEditModal = (offer) => {
    if (offer.offer_status !== 'Draft') {
      setError("Only 'Draft' offer letters can be edited.");
      return;
    }
    setActiveOffer(offer);
    setFormData({
      candidate_id: offer.candidate_id,
      job_opening_id: offer.job_opening_id,
      department_id: offer.department_id || offer.job_opening?.department_id || '',
      position_title: offer.position_title,
      employment_type: offer.employment_type || 'Full-time',
      base_salary: offer.base_salary,
      currency: offer.currency || 'USD',
      salary_period: offer.salary_period || 'Annual',
      offer_date: offer.offer_date ? offer.offer_date.split('T')[0] : '',
      joining_date: offer.joining_date ? offer.joining_date.split('T')[0] : '',
      expiry_date: offer.expiry_date ? offer.expiry_date.split('T')[0] : '',
      terms_and_conditions: offer.terms_and_conditions || '',
      special_allowances: offer.special_allowances || '',
    });
    setFormErrors({});
    setError('');
    setIsFormModalOpen(true);
  };

  // Open Details Modal
  const openDetailModal = (offer) => {
    setActiveOffer(offer);
    setIsDetailModalOpen(true);
  };

  // Open Send Modal
  const openSendModal = (offer) => {
    setActiveOffer(offer);
    setIsSendModalOpen(true);
  };

  // Open Respond Modal (accept, reject, withdraw)
  const openRespondModal = (offer, action) => {
    setActiveOffer(offer);
    setRespondAction(action);
    setRemarks('');
    setRemarksError('');
    setIsRespondModalOpen(true);
  };

  // Open Delete Modal
  const openDeleteModal = (offer) => {
    setActiveOffer(offer);
    setIsDeleteModalOpen(true);
  };

  // Form Submission (Create or Update)
  const handleFormSubmit = async (e) => {
    e.preventDefault();
    setSubmitting(true);
    setFormErrors({});
    setError('');

    try {
      if (activeOffer) {
        // Update draft
        const res = await offerLetterService.updateOfferLetter(activeOffer.id, formData);
        setSuccessMessage(`Offer letter '${res.data?.offer_code || activeOffer.offer_code}' updated successfully.`);
      } else {
        // Create new
        const res = await offerLetterService.createOfferLetter(formData);
        setSuccessMessage(`Offer letter '${res.data?.offer_code || 'new'}' created successfully.`);
      }
      setIsFormModalOpen(false);
      fetchOfferLetters(pagination.currentPage);
    } catch (err) {
      console.error('Form submission error:', err);
      if (err.response?.status === 422 && err.response?.data?.errors) {
        setFormErrors(err.response.data.errors);
      } else {
        setError(err.response?.data?.message || 'Failed to save offer letter.');
      }
    } finally {
      setSubmitting(false);
    }
  };

  // Handle Send
  const handleSend = async () => {
    if (!activeOffer) return;
    setActionLoading(true);
    setError('');
    try {
      const res = await offerLetterService.sendOfferLetter(activeOffer.id);
      setSuccessMessage(res.message || `Offer letter '${activeOffer.offer_code}' sent successfully.`);
      setIsSendModalOpen(false);
      if (isDetailModalOpen && activeOffer?.id === res.data?.id) {
        setActiveOffer(res.data);
      }
      fetchOfferLetters(pagination.currentPage);
    } catch (err) {
      console.error('Send error:', err);
      setError(err.response?.data?.message || 'Failed to send offer letter.');
    } finally {
      setActionLoading(false);
    }
  };

  // Handle Respond (Accept / Reject / Withdraw)
  const handleRespond = async () => {
    if (!activeOffer) return;
    if (respondAction === 'reject' && !remarks.trim()) {
      setRemarksError('Remarks / reason are required when rejecting an offer letter.');
      return;
    }

    setActionLoading(true);
    setError('');
    try {
      let res;
      if (respondAction === 'accept') {
        res = await offerLetterService.acceptOfferLetter(activeOffer.id, remarks.trim());
      } else if (respondAction === 'reject') {
        res = await offerLetterService.rejectOfferLetter(activeOffer.id, remarks.trim());
      } else if (respondAction === 'withdraw') {
        res = await offerLetterService.withdrawOfferLetter(activeOffer.id, remarks.trim());
      }

      setSuccessMessage(res?.message || `Offer letter status updated to ${res?.data?.offer_status}.`);
      setIsRespondModalOpen(false);
      if (isDetailModalOpen && activeOffer?.id === res?.data?.id) {
        setActiveOffer(res.data);
      }
      fetchOfferLetters(pagination.currentPage);
    } catch (err) {
      console.error('Respond error:', err);
      setError(err.response?.data?.message || 'Failed to update offer status.');
    } finally {
      setActionLoading(false);
    }
  };

  // Handle Delete
  const handleDelete = async () => {
    if (!activeOffer) return;
    setActionLoading(true);
    setError('');
    try {
      const res = await offerLetterService.deleteOfferLetter(activeOffer.id);
      setSuccessMessage(res.message || `Offer letter '${activeOffer.offer_code}' deleted.`);
      setIsDeleteModalOpen(false);
      fetchOfferLetters(pagination.currentPage);
    } catch (err) {
      console.error('Delete error:', err);
      setError(err.response?.data?.message || 'Failed to delete offer letter.');
    } finally {
      setActionLoading(false);
    }
  };

  // Handle Download PDF
  const handleDownload = async (offer) => {
    setDownloadingId(offer.id);
    setError('');
    try {
      const response = await offerLetterService.downloadOfferLetter(offer.id);
      const blob = new Blob([response.data], { type: 'application/pdf' });
      const url = window.URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', `${offer.offer_code || 'Offer_Letter'}.pdf`);
      document.body.appendChild(link);
      link.click();
      link.parentNode.removeChild(link);
      window.URL.revokeObjectURL(url);
    } catch (err) {
      console.error('Download error:', err);
      setError(err.response?.data?.message || 'Failed to download offer letter PDF.');
    } finally {
      setDownloadingId(null);
    }
  };

  // Status Badge Styling
  const getStatusBadge = (status) => {
    const map = {
      Draft: 'bg-slate-100 text-slate-700 border-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700',
      Sent: 'bg-sky-50 text-sky-700 border-sky-300 dark:bg-sky-950/50 dark:text-sky-400 dark:border-sky-800',
      Accepted: 'bg-emerald-50 text-emerald-700 border-emerald-300 dark:bg-emerald-950/50 dark:text-emerald-400 dark:border-emerald-800',
      Rejected: 'bg-rose-50 text-rose-700 border-rose-300 dark:bg-rose-950/50 dark:text-rose-400 dark:border-rose-800',
      Withdrawn: 'bg-amber-50 text-amber-700 border-amber-300 dark:bg-amber-950/50 dark:text-amber-400 dark:border-amber-800',
      Expired: 'bg-zinc-100 text-zinc-600 border-zinc-300 dark:bg-zinc-800 dark:text-zinc-400 dark:border-zinc-700',
    };
    return (
      <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border ${map[status] || 'bg-gray-100 text-gray-700'}`}>
        <span className={`w-1.5 h-1.5 rounded-full mr-1.5 ${
          status === 'Accepted' ? 'bg-emerald-500' :
          status === 'Sent' ? 'bg-sky-500 animate-pulse' :
          status === 'Rejected' ? 'bg-rose-500' :
          status === 'Withdrawn' ? 'bg-amber-500' :
          status === 'Expired' ? 'bg-zinc-500' : 'bg-slate-400'
        }`} />
        {status}
      </span>
    );
  };

  // Candidates with Hired status only for the dropdown
  const hiredCandidates = candidates.filter(c => c.status === 'Hired');

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <div className="flex items-center gap-2">
            <span className="px-2.5 py-1 text-xs font-semibold tracking-wider text-indigo-700 uppercase bg-indigo-50 border border-indigo-200 rounded-full dark:bg-indigo-950/50 dark:text-indigo-400 dark:border-indigo-800">
              Recruitment Module
            </span>
          </div>
          <h1 className="text-2xl sm:text-3xl font-bold tracking-tight text-slate-900 dark:text-white mt-1">
            Offer Letters
          </h1>
          <p className="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
            Create, issue, and manage formal candidate employment offers with automated PDF generation.
          </p>
        </div>

        {canCreate && (
          <button
            type="button"
            onClick={openCreateModal}
            className="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white transition-all bg-indigo-600 rounded-lg shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 active:scale-95"
          >
            <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
            </svg>
            Generate Offer Letter
          </button>
        )}
      </div>

      {/* Alerts */}
      {error && (
        <div className="p-4 text-sm text-rose-700 bg-rose-50 border border-rose-200 rounded-xl dark:bg-rose-950/50 dark:text-rose-400 dark:border-rose-900 flex items-start justify-between">
          <div className="flex items-center gap-2">
            <svg className="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
              <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd" />
            </svg>
            <span>{error}</span>
          </div>
          <button onClick={() => setError('')} className="text-rose-500 hover:text-rose-700 font-bold">&times;</button>
        </div>
      )}

      {successMessage && (
        <div className="p-4 text-sm text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-xl dark:bg-emerald-950/50 dark:text-emerald-400 dark:border-emerald-900 flex items-start justify-between">
          <div className="flex items-center gap-2">
            <svg className="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
              <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
            </svg>
            <span>{successMessage}</span>
          </div>
          <button onClick={() => setSuccessMessage('')} className="text-emerald-500 hover:text-emerald-700 font-bold">&times;</button>
        </div>
      )}

      {/* Filter Bar */}
      <div className="p-4 bg-white border border-slate-200 rounded-xl shadow-xs dark:bg-slate-900 dark:border-slate-800 space-y-3">
        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
          {/* Search */}
          <div className="lg:col-span-2">
            <label className="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Search</label>
            <input
              type="text"
              placeholder="Search code, candidate, role..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="w-full px-3 py-1.5 text-sm bg-slate-50 border border-slate-300 rounded-lg dark:bg-slate-800 dark:border-slate-700 text-slate-900 dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-indigo-500 focus:outline-none"
            />
          </div>

          {/* Status */}
          <div>
            <label className="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Status</label>
            <select
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value)}
              className="w-full px-3 py-1.5 text-sm bg-slate-50 border border-slate-300 rounded-lg dark:bg-slate-800 dark:border-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none"
            >
              <option value="">All Statuses</option>
              {OFFER_STATUSES.map(st => (
                <option key={st} value={st}>{st}</option>
              ))}
            </select>
          </div>

          {/* Candidate */}
          <div>
            <label className="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Candidate</label>
            <select
              value={candidateFilter}
              onChange={(e) => setCandidateFilter(e.target.value)}
              className="w-full px-3 py-1.5 text-sm bg-slate-50 border border-slate-300 rounded-lg dark:bg-slate-800 dark:border-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none"
            >
              <option value="">All Candidates</option>
              {candidates.map(c => (
                <option key={c.id} value={c.id}>
                  {c.first_name} {c.last_name} ({c.candidate_code})
                </option>
              ))}
            </select>
          </div>

          {/* Offer Date Range */}
          <div>
            <label className="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">From Date</label>
            <input
              type="date"
              value={fromDateFilter}
              onChange={(e) => setFromDateFilter(e.target.value)}
              className="w-full px-3 py-1.5 text-sm bg-slate-50 border border-slate-300 rounded-lg dark:bg-slate-800 dark:border-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none"
            />
          </div>
          <div>
            <label className="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">To Date</label>
            <input
              type="date"
              value={toDateFilter}
              onChange={(e) => setToDateFilter(e.target.value)}
              className="w-full px-3 py-1.5 text-sm bg-slate-50 border border-slate-300 rounded-lg dark:bg-slate-800 dark:border-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none"
            />
          </div>
        </div>

        {/* Clear Filters Button */}
        {(search || statusFilter || candidateFilter || jobFilter || fromDateFilter || toDateFilter) && (
          <div className="flex justify-end pt-2 border-t border-slate-100 dark:border-slate-800">
            <button
              onClick={() => {
                setSearch('');
                setStatusFilter('');
                setCandidateFilter('');
                setJobFilter('');
                setFromDateFilter('');
                setToDateFilter('');
              }}
              className="text-xs font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200"
            >
              Reset Filters
            </button>
          </div>
        )}
      </div>

      {/* Main Table */}
      <div className="bg-white border border-slate-200 rounded-xl shadow-xs dark:bg-slate-900 dark:border-slate-800 overflow-hidden">
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
            <thead className="bg-slate-50 dark:bg-slate-800/50">
              <tr>
                <th scope="col" className="px-4 py-3.5 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                  Offer Code
                </th>
                <th scope="col" className="px-4 py-3.5 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                  Candidate
                </th>
                <th scope="col" className="px-4 py-3.5 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                  Position & Type
                </th>
                <th scope="col" className="px-4 py-3.5 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                  Compensation
                </th>
                <th scope="col" className="px-4 py-3.5 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                  Dates
                </th>
                <th scope="col" className="px-4 py-3.5 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                  Status
                </th>
                <th scope="col" className="px-4 py-3.5 text-right text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-200 dark:divide-slate-800">
              {loading ? (
                <tr>
                  <td colSpan="7" className="px-4 py-12 text-center text-slate-500 dark:text-slate-400">
                    <div className="inline-flex items-center gap-2">
                      <svg className="w-5 h-5 animate-spin text-indigo-600" fill="none" viewBox="0 0 24 24">
                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                      </svg>
                      <span>Loading offer letters...</span>
                    </div>
                  </td>
                </tr>
              ) : offerLetters.length === 0 ? (
                <tr>
                  <td colSpan="7" className="px-4 py-12 text-center text-slate-500 dark:text-slate-400">
                    <div className="max-w-sm mx-auto space-y-2">
                      <svg className="w-12 h-12 mx-auto text-slate-300 dark:text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                      </svg>
                      <p className="text-sm font-medium text-slate-700 dark:text-slate-300">No offer letters found</p>
                      <p className="text-xs text-slate-400">Try adjusting your search criteria or create an offer for a Hired candidate.</p>
                    </div>
                  </td>
                </tr>
              ) : (
                offerLetters.map((offer) => {
                  const isDraft = offer.offer_status === 'Draft';
                  const isSent = offer.offer_status === 'Sent';

                  return (
                    <tr key={offer.id} className="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors">
                      {/* Offer Code */}
                      <td className="px-4 py-3.5 whitespace-nowrap">
                        <div className="flex items-center gap-2">
                          <span className="font-mono text-xs font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/40 px-2 py-0.5 rounded border border-indigo-200 dark:border-indigo-800">
                            {offer.offer_code}
                          </span>
                        </div>
                        <div className="text-[11px] text-slate-400 mt-0.5">
                          {offer.created_at ? new Date(offer.created_at).toLocaleDateString() : ''}
                        </div>
                      </td>

                      {/* Candidate */}
                      <td className="px-4 py-3.5">
                        <div className="font-medium text-slate-900 dark:text-white text-sm">
                          {offer.candidate ? `${offer.candidate.first_name} ${offer.candidate.last_name}` : 'Unknown'}
                        </div>
                        <div className="text-xs text-slate-400">
                          {offer.candidate?.email || offer.candidate?.candidate_code}
                        </div>
                      </td>

                      {/* Position & Type */}
                      <td className="px-4 py-3.5">
                        <div className="text-sm font-medium text-slate-800 dark:text-slate-200">
                          {offer.position_title}
                        </div>
                        <div className="text-xs text-slate-400 flex items-center gap-1.5">
                          <span>{offer.job_opening?.title || 'Direct'}</span>
                          <span>&bull;</span>
                          <span className="font-medium text-slate-600 dark:text-slate-300">{offer.employment_type}</span>
                        </div>
                      </td>

                      {/* Compensation */}
                      <td className="px-4 py-3.5 whitespace-nowrap">
                        <div className="text-sm font-bold text-slate-900 dark:text-white">
                          {offer.currency} {Number(offer.base_salary).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                        </div>
                        <div className="text-xs text-slate-400">
                          {offer.salary_period}
                        </div>
                      </td>

                      {/* Dates */}
                      <td className="px-4 py-3.5 whitespace-nowrap text-xs text-slate-500 dark:text-slate-400">
                        <div>
                          <span className="font-semibold text-slate-600 dark:text-slate-300">Join: </span>
                          {offer.joining_date ? new Date(offer.joining_date).toLocaleDateString() : '—'}
                        </div>
                        {offer.expiry_date && (
                          <div className="text-[11px] text-slate-400">
                            <span>Expires: </span>
                            {new Date(offer.expiry_date).toLocaleDateString()}
                          </div>
                        )}
                      </td>

                      {/* Status */}
                      <td className="px-4 py-3.5 whitespace-nowrap">
                        {getStatusBadge(offer.offer_status)}
                      </td>

                      {/* Actions */}
                      <td className="px-4 py-3.5 whitespace-nowrap text-right text-sm">
                        <div className="inline-flex items-center gap-1.5">
                          {/* View Details */}
                          <button
                            type="button"
                            onClick={() => openDetailModal(offer)}
                            className="px-2.5 py-1 text-xs font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 dark:text-slate-300 dark:bg-slate-800 dark:hover:bg-slate-700 rounded-md transition"
                            title="View Full Details"
                          >
                            Details
                          </button>

                          {/* Download PDF */}
                          {canDownload && (
                            <button
                              type="button"
                              onClick={() => handleDownload(offer)}
                              disabled={downloadingId === offer.id}
                              className="px-2 py-1 text-xs font-medium text-indigo-700 bg-indigo-50 hover:bg-indigo-100 dark:text-indigo-400 dark:bg-indigo-950/40 dark:hover:bg-indigo-900/50 rounded-md transition inline-flex items-center gap-1"
                              title="Download PDF Document"
                            >
                              {downloadingId === offer.id ? (
                                <svg className="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                  <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                  <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                                </svg>
                              ) : (
                                <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                              )}
                              PDF
                            </button>
                          )}

                          {/* Send (Draft only) */}
                          {isDraft && canSend && (
                            <button
                              type="button"
                              onClick={() => openSendModal(offer)}
                              className="px-2.5 py-1 text-xs font-medium text-sky-700 bg-sky-50 hover:bg-sky-100 dark:text-sky-300 dark:bg-sky-950/40 dark:hover:bg-sky-900/50 rounded-md transition"
                            >
                              Send
                            </button>
                          )}

                          {/* Accept (Sent only) */}
                          {isSent && canRespond && (
                            <button
                              type="button"
                              onClick={() => openRespondModal(offer, 'accept')}
                              className="px-2.5 py-1 text-xs font-medium text-emerald-700 bg-emerald-50 hover:bg-emerald-100 dark:text-emerald-300 dark:bg-emerald-950/40 dark:hover:bg-emerald-900/50 rounded-md transition"
                            >
                              Accept
                            </button>
                          )}

                          {/* Reject (Sent only) */}
                          {isSent && canRespond && (
                            <button
                              type="button"
                              onClick={() => openRespondModal(offer, 'reject')}
                              className="px-2.5 py-1 text-xs font-medium text-rose-700 bg-rose-50 hover:bg-rose-100 dark:text-rose-300 dark:bg-rose-950/40 dark:hover:bg-rose-900/50 rounded-md transition"
                            >
                              Reject
                            </button>
                          )}

                          {/* Edit (Draft only) */}
                          {isDraft && canUpdate && (
                            <button
                              type="button"
                              onClick={() => openEditModal(offer)}
                              className="px-2 py-1 text-xs font-medium text-amber-700 bg-amber-50 hover:bg-amber-100 dark:text-amber-400 dark:bg-amber-950/40 dark:hover:bg-amber-900/50 rounded-md transition"
                              title="Edit Draft"
                            >
                              Edit
                            </button>
                          )}

                          {/* Delete (Draft only, Super Admin & HR Admin only) */}
                          {isDraft && canDelete && (
                            <button
                              type="button"
                              onClick={() => openDeleteModal(offer)}
                              className="px-2 py-1 text-xs font-medium text-rose-700 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950/40 rounded-md transition"
                              title="Delete Draft"
                            >
                              Delete
                            </button>
                          )}
                        </div>
                      </td>
                    </tr>
                  );
                })
              )}
            </tbody>
          </table>
        </div>

        {/* Pagination */}
        {pagination.total > 0 && (
          <div className="px-4 py-3 bg-white border-t border-slate-200 dark:bg-slate-900 dark:border-slate-800 flex items-center justify-between">
            <div className="text-xs text-slate-500 dark:text-slate-400">
              Showing <span className="font-semibold text-slate-700 dark:text-slate-200">{((pagination.currentPage - 1) * pagination.perPage) + 1}</span> to{' '}
              <span className="font-semibold text-slate-700 dark:text-slate-200">
                {Math.min(pagination.currentPage * pagination.perPage, pagination.total)}
              </span> of <span className="font-semibold text-slate-700 dark:text-slate-200">{pagination.total}</span> offers
            </div>
            <div className="inline-flex gap-1">
              <button
                type="button"
                disabled={pagination.currentPage <= 1}
                onClick={() => fetchOfferLetters(pagination.currentPage - 1)}
                className="px-3 py-1 text-xs font-medium text-slate-700 bg-slate-100 rounded hover:bg-slate-200 disabled:opacity-40 disabled:cursor-not-allowed dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
              >
                Previous
              </button>
              <button
                type="button"
                disabled={pagination.currentPage >= pagination.lastPage}
                onClick={() => fetchOfferLetters(pagination.currentPage + 1)}
                className="px-3 py-1 text-xs font-medium text-slate-700 bg-slate-100 rounded hover:bg-slate-200 disabled:opacity-40 disabled:cursor-not-allowed dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
              >
                Next
              </button>
            </div>
          </div>
        )}
      </div>

      {/* CREATE / EDIT MODAL */}
      {isFormModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto">
          <div className="w-full max-w-2xl my-8 bg-white rounded-2xl shadow-xl dark:bg-slate-900 border border-slate-200 dark:border-slate-800 overflow-hidden">
            <div className="px-6 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between bg-slate-50 dark:bg-slate-800/50">
              <div>
                <h3 className="text-lg font-bold text-slate-900 dark:text-white">
                  {activeOffer ? `Edit Draft Offer (${activeOffer.offer_code})` : 'Create New Offer Letter'}
                </h3>
                <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                  Offers can only be generated for candidates with 'Hired' status.
                </p>
              </div>
              <button
                type="button"
                onClick={() => setIsFormModalOpen(false)}
                className="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-xl font-bold"
              >
                &times;
              </button>
            </div>

            <form onSubmit={handleFormSubmit} className="p-6 space-y-4 max-h-[75vh] overflow-y-auto">
              {/* Candidate Selection */}
              <div>
                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                  Candidate (Hired status required) <span className="text-rose-500">*</span>
                </label>
                {activeOffer ? (
                  <input
                    type="text"
                    disabled
                    value={`${activeOffer.candidate?.first_name} ${activeOffer.candidate?.last_name} (${activeOffer.candidate?.candidate_code})`}
                    className="w-full px-3 py-2 text-sm bg-slate-100 border border-slate-300 rounded-lg dark:bg-slate-800 dark:border-slate-700 text-slate-500"
                  />
                ) : (
                  <select
                    value={formData.candidate_id}
                    onChange={handleCandidateChange}
                    required
                    className="w-full px-3 py-2 text-sm bg-white border border-slate-300 rounded-lg dark:bg-slate-800 dark:border-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                  >
                    <option value="">Select eligible Hired candidate...</option>
                    {hiredCandidates.map(c => (
                      <option key={c.id} value={c.id}>
                        {c.first_name} {c.last_name} — {c.job_opening?.title || 'Position'} ({c.candidate_code})
                      </option>
                    ))}
                  </select>
                )}
                {formErrors.candidate_id && (
                  <p className="text-xs text-rose-600 mt-1">{formErrors.candidate_id[0]}</p>
                )}
              </div>

              {/* Job Opening & Department (Locked / Auto-populated) */}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    Job Opening <span className="text-rose-500">*</span>
                  </label>
                  <select
                    value={formData.job_opening_id}
                    disabled
                    className="w-full px-3 py-2 text-sm bg-slate-100 border border-slate-300 rounded-lg dark:bg-slate-800 dark:border-slate-700 text-slate-600 dark:text-slate-400"
                  >
                    <option value="">Auto-bound from candidate</option>
                    {jobOpenings.map(j => (
                      <option key={j.id} value={j.id}>{j.title} ({j.job_code})</option>
                    ))}
                  </select>
                  {formErrors.job_opening_id && (
                    <p className="text-xs text-rose-600 mt-1">{formErrors.job_opening_id[0]}</p>
                  )}
                </div>

                <div>
                  <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    Department
                  </label>
                  <select
                    value={formData.department_id}
                    disabled
                    className="w-full px-3 py-2 text-sm bg-slate-100 border border-slate-300 rounded-lg dark:bg-slate-800 dark:border-slate-700 text-slate-600 dark:text-slate-400"
                  >
                    <option value="">Auto-bound from Job Opening</option>
                    {departments.map(d => (
                      <option key={d.id} value={d.id}>{d.name}</option>
                    ))}
                  </select>
                </div>
              </div>

              {/* Position Title & Employment Type */}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    Position Title <span className="text-rose-500">*</span>
                  </label>
                  <input
                    type="text"
                    required
                    value={formData.position_title}
                    onChange={(e) => setFormData({ ...formData, position_title: e.target.value })}
                    placeholder="e.g. Senior Software Engineer"
                    className="w-full px-3 py-2 text-sm bg-white border border-slate-300 rounded-lg dark:bg-slate-800 dark:border-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                  />
                  {formErrors.position_title && (
                    <p className="text-xs text-rose-600 mt-1">{formErrors.position_title[0]}</p>
                  )}
                </div>

                <div>
                  <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    Employment Type <span className="text-rose-500">*</span>
                  </label>
                  <select
                    value={formData.employment_type}
                    onChange={(e) => setFormData({ ...formData, employment_type: e.target.value })}
                    className="w-full px-3 py-2 text-sm bg-white border border-slate-300 rounded-lg dark:bg-slate-800 dark:border-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                  >
                    {EMPLOYMENT_TYPES.map(t => (
                      <option key={t} value={t}>{t}</option>
                    ))}
                  </select>
                </div>
              </div>

              {/* Compensation */}
              <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                  <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    Base Salary <span className="text-rose-500">*</span>
                  </label>
                  <input
                    type="number"
                    step="0.01"
                    min="0"
                    required
                    value={formData.base_salary}
                    onChange={(e) => setFormData({ ...formData, base_salary: e.target.value })}
                    placeholder="e.g. 95000"
                    className="w-full px-3 py-2 text-sm bg-white border border-slate-300 rounded-lg dark:bg-slate-800 dark:border-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                  />
                  {formErrors.base_salary && (
                    <p className="text-xs text-rose-600 mt-1">{formErrors.base_salary[0]}</p>
                  )}
                </div>

                <div>
                  <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    Currency
                  </label>
                  <input
                    type="text"
                    maxLength="3"
                    value={formData.currency}
                    onChange={(e) => setFormData({ ...formData, currency: e.target.value.toUpperCase() })}
                    placeholder="USD"
                    className="w-full px-3 py-2 text-sm bg-white border border-slate-300 rounded-lg dark:bg-slate-800 dark:border-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none uppercase"
                  />
                </div>

                <div>
                  <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    Frequency
                  </label>
                  <select
                    value={formData.salary_period}
                    onChange={(e) => setFormData({ ...formData, salary_period: e.target.value })}
                    className="w-full px-3 py-2 text-sm bg-white border border-slate-300 rounded-lg dark:bg-slate-800 dark:border-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                  >
                    {SALARY_PERIODS.map(p => (
                      <option key={p} value={p}>{p}</option>
                    ))}
                  </select>
                </div>
              </div>

              {/* Dates */}
              <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                  <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    Offer Date <span className="text-rose-500">*</span>
                  </label>
                  <input
                    type="date"
                    required
                    value={formData.offer_date}
                    onChange={(e) => setFormData({ ...formData, offer_date: e.target.value })}
                    className="w-full px-3 py-2 text-sm bg-white border border-slate-300 rounded-lg dark:bg-slate-800 dark:border-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                  />
                  {formErrors.offer_date && (
                    <p className="text-xs text-rose-600 mt-1">{formErrors.offer_date[0]}</p>
                  )}
                </div>

                <div>
                  <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    Joining Date <span className="text-rose-500">*</span>
                  </label>
                  <input
                    type="date"
                    required
                    value={formData.joining_date}
                    onChange={(e) => setFormData({ ...formData, joining_date: e.target.value })}
                    className="w-full px-3 py-2 text-sm bg-white border border-slate-300 rounded-lg dark:bg-slate-800 dark:border-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                  />
                  {formErrors.joining_date && (
                    <p className="text-xs text-rose-600 mt-1">{formErrors.joining_date[0]}</p>
                  )}
                </div>

                <div>
                  <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    Expiry Date
                  </label>
                  <input
                    type="date"
                    value={formData.expiry_date}
                    onChange={(e) => setFormData({ ...formData, expiry_date: e.target.value })}
                    className="w-full px-3 py-2 text-sm bg-white border border-slate-300 rounded-lg dark:bg-slate-800 dark:border-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                  />
                  {formErrors.expiry_date && (
                    <p className="text-xs text-rose-600 mt-1">{formErrors.expiry_date[0]}</p>
                  )}
                </div>
              </div>

              {/* Special Allowances */}
              <div>
                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                  Special Allowances / Bonus Details
                </label>
                <textarea
                  rows="2"
                  value={formData.special_allowances}
                  onChange={(e) => setFormData({ ...formData, special_allowances: e.target.value })}
                  placeholder="e.g. Sign-on bonus $5,000; Annual performance bonus up to 10%."
                  className="w-full px-3 py-2 text-sm bg-white border border-slate-300 rounded-lg dark:bg-slate-800 dark:border-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                />
              </div>

              {/* Terms and Conditions */}
              <div>
                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                  Terms & Conditions
                </label>
                <textarea
                  rows="3"
                  value={formData.terms_and_conditions}
                  onChange={(e) => setFormData({ ...formData, terms_and_conditions: e.target.value })}
                  className="w-full px-3 py-2 text-sm bg-white border border-slate-300 rounded-lg dark:bg-slate-800 dark:border-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                />
              </div>

              {/* Modal Actions */}
              <div className="flex justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-800">
                <button
                  type="button"
                  onClick={() => setIsFormModalOpen(false)}
                  className="px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 dark:text-slate-300 dark:bg-slate-800 dark:hover:bg-slate-700 rounded-lg transition"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={submitting}
                  className="px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition shadow-sm disabled:opacity-50 inline-flex items-center gap-2"
                >
                  {submitting && (
                    <svg className="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                      <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                      <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                    </svg>
                  )}
                  {activeOffer ? 'Update Draft' : 'Save Offer Draft'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* DETAIL MODAL */}
      {isDetailModalOpen && activeOffer && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto">
          <div className="w-full max-w-2xl my-8 bg-white rounded-2xl shadow-xl dark:bg-slate-900 border border-slate-200 dark:border-slate-800 overflow-hidden">
            {/* Modal Header */}
            <div className="px-6 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between bg-slate-50 dark:bg-slate-800/50">
              <div className="flex items-center gap-3">
                <span className="font-mono text-sm font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/40 px-2.5 py-1 rounded border border-indigo-200 dark:border-indigo-800">
                  {activeOffer.offer_code}
                </span>
                {getStatusBadge(activeOffer.offer_status)}
              </div>
              <button
                type="button"
                onClick={() => setIsDetailModalOpen(false)}
                className="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-xl font-bold"
              >
                &times;
              </button>
            </div>

            {/* Modal Content */}
            <div className="p-6 space-y-6 max-h-[75vh] overflow-y-auto">
              {/* Candidate & Role Grid */}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 p-4 bg-slate-50 dark:bg-slate-800/40 rounded-xl border border-slate-200/60 dark:border-slate-700/60">
                <div>
                  <p className="text-xs text-slate-400 uppercase tracking-wider font-semibold">Candidate</p>
                  <p className="text-sm font-bold text-slate-900 dark:text-white mt-0.5">
                    {activeOffer.candidate?.first_name} {activeOffer.candidate?.last_name}
                  </p>
                  <p className="text-xs text-slate-500">{activeOffer.candidate?.email}</p>
                  <p className="text-xs text-slate-400 mt-1">Code: {activeOffer.candidate?.candidate_code}</p>
                </div>
                <div>
                  <p className="text-xs text-slate-400 uppercase tracking-wider font-semibold">Position & Department</p>
                  <p className="text-sm font-bold text-slate-900 dark:text-white mt-0.5">{activeOffer.position_title}</p>
                  <p className="text-xs text-slate-500">{activeOffer.department?.name || activeOffer.job_opening?.department?.name || 'Department'}</p>
                  <p className="text-xs text-slate-400 mt-1">Opening: {activeOffer.job_opening?.title} ({activeOffer.job_opening?.job_code})</p>
                </div>
              </div>

              {/* Compensation Details */}
              <div className="p-4 bg-emerald-50/50 dark:bg-emerald-950/20 rounded-xl border border-emerald-200/60 dark:border-emerald-900/40">
                <p className="text-xs text-emerald-800 dark:text-emerald-300 uppercase tracking-wider font-semibold">Compensation Package</p>
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-2">
                  <div>
                    <span className="text-xs text-slate-500">Base Salary:</span>
                    <p className="text-base font-bold text-emerald-700 dark:text-emerald-400">
                      {activeOffer.currency} {Number(activeOffer.base_salary).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                    </p>
                  </div>
                  <div>
                    <span className="text-xs text-slate-500">Frequency:</span>
                    <p className="text-sm font-semibold text-slate-800 dark:text-slate-200">{activeOffer.salary_period}</p>
                  </div>
                  <div>
                    <span className="text-xs text-slate-500">Type:</span>
                    <p className="text-sm font-semibold text-slate-800 dark:text-slate-200">{activeOffer.employment_type}</p>
                  </div>
                </div>
                {activeOffer.special_allowances && (
                  <div className="mt-3 pt-2 border-t border-emerald-200/40 dark:border-emerald-900/30">
                    <span className="text-xs font-semibold text-emerald-900 dark:text-emerald-300">Special Allowances:</span>
                    <p className="text-xs text-slate-700 dark:text-slate-300 mt-0.5 whitespace-pre-line">{activeOffer.special_allowances}</p>
                  </div>
                )}
              </div>

              {/* Dates Timeline */}
              <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 p-3 bg-slate-50 dark:bg-slate-800/40 rounded-xl text-xs">
                <div>
                  <span className="text-slate-400 block">Offer Date:</span>
                  <span className="font-semibold text-slate-700 dark:text-slate-300">{activeOffer.offer_date ? new Date(activeOffer.offer_date).toLocaleDateString() : '—'}</span>
                </div>
                <div>
                  <span className="text-slate-400 block">Joining Date:</span>
                  <span className="font-semibold text-slate-700 dark:text-slate-300">{activeOffer.joining_date ? new Date(activeOffer.joining_date).toLocaleDateString() : '—'}</span>
                </div>
                <div>
                  <span className="text-slate-400 block">Sent At:</span>
                  <span className="font-semibold text-slate-700 dark:text-slate-300">{activeOffer.sent_at ? new Date(activeOffer.sent_at).toLocaleString() : 'Not Sent'}</span>
                </div>
                <div>
                  <span className="text-slate-400 block">Expiry Date:</span>
                  <span className="font-semibold text-slate-700 dark:text-slate-300">{activeOffer.expiry_date ? new Date(activeOffer.expiry_date).toLocaleDateString() : 'No Expiry'}</span>
                </div>
              </div>

              {/* Response Remarks if available */}
              {activeOffer.response_remarks && (
                <div className="p-3 bg-slate-50 dark:bg-slate-800/40 rounded-xl border border-slate-200 dark:border-slate-700 text-xs">
                  <span className="font-semibold text-slate-700 dark:text-slate-300 block mb-1">
                    Response Remarks ({activeOffer.responded_at ? new Date(activeOffer.responded_at).toLocaleString() : ''}):
                  </span>
                  <p className="text-slate-600 dark:text-slate-300 whitespace-pre-line">{activeOffer.response_remarks}</p>
                </div>
              )}

              {/* Terms & Conditions */}
              {activeOffer.terms_and_conditions && (
                <div className="p-3 bg-slate-50 dark:bg-slate-800/40 rounded-xl border border-slate-200 dark:border-slate-700 text-xs">
                  <span className="font-semibold text-slate-700 dark:text-slate-300 block mb-1">Terms & Conditions:</span>
                  <p className="text-slate-600 dark:text-slate-300 whitespace-pre-line">{activeOffer.terms_and_conditions}</p>
                </div>
              )}
            </div>

            {/* Modal Actions */}
            <div className="px-6 py-4 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-200 dark:border-slate-800 flex flex-wrap items-center justify-between gap-3">
              <div>
                {canDownload && (
                  <button
                    type="button"
                    onClick={() => handleDownload(activeOffer)}
                    disabled={downloadingId === activeOffer.id}
                    className="px-3 py-1.5 text-xs font-semibold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 dark:text-indigo-400 dark:bg-indigo-950/40 rounded-lg inline-flex items-center gap-1.5 transition"
                  >
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    Download Official PDF
                  </button>
                )}
              </div>

              <div className="flex items-center gap-2">
                {activeOffer.offer_status === 'Draft' && canSend && (
                  <button
                    type="button"
                    onClick={() => { setIsDetailModalOpen(false); openSendModal(activeOffer); }}
                    className="px-3 py-1.5 text-xs font-semibold text-white bg-sky-600 hover:bg-sky-700 rounded-lg shadow-xs transition"
                  >
                    Send to Candidate
                  </button>
                )}

                {activeOffer.offer_status === 'Sent' && canRespond && (
                  <>
                    <button
                      type="button"
                      onClick={() => { setIsDetailModalOpen(false); openRespondModal(activeOffer, 'accept'); }}
                      className="px-3 py-1.5 text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg shadow-xs transition"
                    >
                      Mark Accepted
                    </button>
                    <button
                      type="button"
                      onClick={() => { setIsDetailModalOpen(false); openRespondModal(activeOffer, 'reject'); }}
                      className="px-3 py-1.5 text-xs font-semibold text-white bg-rose-600 hover:bg-rose-700 rounded-lg shadow-xs transition"
                    >
                      Mark Rejected
                    </button>
                    <button
                      type="button"
                      onClick={() => { setIsDetailModalOpen(false); openRespondModal(activeOffer, 'withdraw'); }}
                      className="px-3 py-1.5 text-xs font-semibold text-amber-800 bg-amber-100 hover:bg-amber-200 dark:bg-amber-950/50 dark:text-amber-300 rounded-lg transition"
                    >
                      Withdraw Offer
                    </button>
                  </>
                )}

                <button
                  type="button"
                  onClick={() => setIsDetailModalOpen(false)}
                  className="px-3 py-1.5 text-xs font-semibold text-slate-700 bg-slate-200 hover:bg-slate-300 dark:text-slate-300 dark:bg-slate-800 rounded-lg transition"
                >
                  Close
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* SEND CONFIRMATION MODAL */}
      {isSendModalOpen && activeOffer && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
          <div className="w-full max-w-md bg-white rounded-2xl shadow-xl dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 space-y-4">
            <div className="w-12 h-12 rounded-full bg-sky-100 dark:bg-sky-950/60 flex items-center justify-center text-sky-600 dark:text-sky-400 mx-auto">
              <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
              </svg>
            </div>
            <div className="text-center">
              <h3 className="text-base font-bold text-slate-900 dark:text-white">
                Send Offer Letter {activeOffer.offer_code}?
              </h3>
              <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                This will finalize the offer document and transition status from <strong>Draft</strong> to <strong>Sent</strong>. The candidate will be notified.
              </p>
            </div>
            <div className="flex justify-end gap-3 pt-2">
              <button
                type="button"
                onClick={() => setIsSendModalOpen(false)}
                className="px-4 py-2 text-xs font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 dark:text-slate-300 dark:bg-slate-800 rounded-lg transition"
              >
                Cancel
              </button>
              <button
                type="button"
                disabled={actionLoading}
                onClick={handleSend}
                className="px-4 py-2 text-xs font-medium text-white bg-sky-600 hover:bg-sky-700 rounded-lg shadow-sm disabled:opacity-50 inline-flex items-center gap-2"
              >
                {actionLoading && (
                  <svg className="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                  </svg>
                )}
                Confirm & Send
              </button>
            </div>
          </div>
        </div>
      )}

      {/* RESPOND MODAL (Accept / Reject / Withdraw) */}
      {isRespondModalOpen && activeOffer && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
          <div className="w-full max-w-md bg-white rounded-2xl shadow-xl dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 space-y-4">
            <div className="text-center">
              <h3 className="text-base font-bold text-slate-900 dark:text-white capitalize">
                {respondAction === 'accept' && 'Mark Offer as Accepted'}
                {respondAction === 'reject' && 'Mark Offer as Rejected'}
                {respondAction === 'withdraw' && 'Withdraw Offer Letter'}
              </h3>
              <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                {respondAction === 'accept' && 'Record the candidate’s formal acceptance. Note: Candidate status remains Hired.'}
                {respondAction === 'reject' && 'Record the candidate’s rejection. Mandatory reason/remarks required.'}
                {respondAction === 'withdraw' && 'Withdraw the issued offer letter from the candidate.'}
              </p>
            </div>

            <div>
              <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                Remarks / Reason {respondAction === 'reject' && <span className="text-rose-500">* (Required)</span>}
              </label>
              <textarea
                rows="3"
                value={remarks}
                onChange={(e) => {
                  setRemarks(e.target.value);
                  if (remarksError) setRemarksError('');
                }}
                placeholder={
                  respondAction === 'reject'
                    ? 'State reasons for candidate offer rejection...'
                    : 'Add optional internal notes or remarks...'
                }
                className="w-full px-3 py-2 text-sm bg-white border border-slate-300 rounded-lg dark:bg-slate-800 dark:border-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none"
              />
              {remarksError && <p className="text-xs text-rose-600 mt-1">{remarksError}</p>}
            </div>

            <div className="flex justify-end gap-3 pt-2">
              <button
                type="button"
                onClick={() => setIsRespondModalOpen(false)}
                className="px-4 py-2 text-xs font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 dark:text-slate-300 dark:bg-slate-800 rounded-lg transition"
              >
                Cancel
              </button>
              <button
                type="button"
                disabled={actionLoading}
                onClick={handleRespond}
                className={`px-4 py-2 text-xs font-medium text-white rounded-lg shadow-sm disabled:opacity-50 inline-flex items-center gap-2 ${
                  respondAction === 'accept'
                    ? 'bg-emerald-600 hover:bg-emerald-700'
                    : respondAction === 'reject'
                    ? 'bg-rose-600 hover:bg-rose-700'
                    : 'bg-amber-600 hover:bg-amber-700'
                }`}
              >
                {actionLoading && (
                  <svg className="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                  </svg>
                )}
                Confirm {respondAction}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* DELETE CONFIRMATION MODAL */}
      {isDeleteModalOpen && activeOffer && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
          <div className="w-full max-w-sm bg-white rounded-2xl shadow-xl dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 space-y-4">
            <div className="w-12 h-12 rounded-full bg-rose-100 dark:bg-rose-950/60 flex items-center justify-center text-rose-600 dark:text-rose-400 mx-auto">
              <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
            </div>
            <div className="text-center">
              <h3 className="text-base font-bold text-slate-900 dark:text-white">
                Delete Draft Offer?
              </h3>
              <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                Are you sure you want to delete draft offer <strong>{activeOffer.offer_code}</strong>? This action cannot be undone.
              </p>
            </div>
            <div className="flex justify-end gap-3 pt-2">
              <button
                type="button"
                onClick={() => setIsDeleteModalOpen(false)}
                className="px-4 py-2 text-xs font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 dark:text-slate-300 dark:bg-slate-800 rounded-lg transition"
              >
                Cancel
              </button>
              <button
                type="button"
                disabled={actionLoading}
                onClick={handleDelete}
                className="px-4 py-2 text-xs font-medium text-white bg-rose-600 hover:bg-rose-700 rounded-lg shadow-sm disabled:opacity-50 inline-flex items-center gap-2"
              >
                {actionLoading && (
                  <svg className="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                  </svg>
                )}
                Delete Offer
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default OfferLetters;
