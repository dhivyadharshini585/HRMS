import { describe, it, expect, vi, beforeEach } from 'vitest';
import api from '../services/api';
import store from '../store';
import { loginSuccess } from '../store/authSlice';

// Mock react-toastify
vi.mock('react-toastify', () => ({
  toast: {
    error: vi.fn(),
  },
}));

describe('Axios API Interceptors', () => {
  beforeEach(() => {
    localStorage.clear();
    vi.clearAllMocks();
  });

  it('should attach Bearer token to request if token exists in localStorage', () => {
    localStorage.setItem('token', 'test-token-123');
    const config = { headers: {} };
    
    // Simulate the interceptor call directly
    // The request interceptors array in axios is internal, but we can test it 
    // by triggering it manually or using moxios/nock.
    // For simplicity, let's just test the logic registered in interceptors.
    
    // Axios stores request interceptors in api.interceptors.request.handlers
    const requestHandler = api.interceptors.request.handlers[0].fulfilled;
    const result = requestHandler(config);
    
    expect(result.headers.Authorization).toBe('Bearer test-token-123');
  });

  it('should clear localStorage and dispatch logout on 401 response', () => {
    // Setup state
    localStorage.setItem('token', 'expired-token');
    store.dispatch(loginSuccess({ token: 'expired-token', user: { name: 'Test' }, roles: [] }));
    
    // Verify initial state
    expect(store.getState().auth.isAuthenticated).toBe(true);
    
    // Simulate 401 response
    const errorResponse = {
      response: {
        status: 401,
      }
    };
    
    const responseHandlerError = api.interceptors.response.handlers[0].rejected;
    
    // We expect this to reject, so we catch it
    await expect(responseHandlerError(errorResponse)).rejects.toEqual(errorResponse);
    
    // Verify side effects
    expect(localStorage.getItem('token')).toBeNull();
    expect(store.getState().auth.isAuthenticated).toBe(false);
  });
});
