import api from './api';

export const aiService = {
  screenResume: async (candidateId) => {
    const response = await api.post('/ai/resume-screen', { candidate_id: candidateId });
    return response.data;
  },

  askHRAssistant: async (question) => {
    const response = await api.post('/ai/hr-assistant', { question });
    return response.data;
  }
};
