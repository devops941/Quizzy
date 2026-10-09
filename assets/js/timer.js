/**
 * Quizzy Quiz Timer
 * Handles countdown timer with localStorage persistence, visual warnings, and auto-submission
 *
 * Required globals (set by PHP in attempt.php):
 *   - window.QUIZ_TIME_REMAINING (seconds)
 *   - window.QUIZ_SUBMIT_FORM_ID (form id for auto-submit)
 *   - window.QUIZ_ATTEMPT_ID (attempt id for localStorage key)
 */

(function() {
  'use strict';

  // Configuration
  const WARNING_THRESHOLD = 60; // seconds before showing red/bold
  const TIMER_ELEMENT_ID = 'quiz-timer';
  const PULSE_CLASS = 'pulse-animation';
  const WARNING_CLASS = 'text-danger';
  const BOLD_CLASS = 'fw-bold';

  // State
  let timeRemaining = 0;
  let timerInterval = null;
  let hasSubmitted = false;

  /**
   * Initialize timer from PHP globals or localStorage
   */
  function initializeTimer() {
    if (typeof window.QUIZ_TIME_REMAINING === 'undefined') {
      console.error('QUIZ_TIME_REMAINING not set by PHP');
      return false;
    }
    if (typeof window.QUIZ_ATTEMPT_ID === 'undefined') {
      console.error('QUIZ_ATTEMPT_ID not set by PHP');
      return false;
    }
    if (typeof window.QUIZ_SUBMIT_FORM_ID === 'undefined') {
      console.error('QUIZ_SUBMIT_FORM_ID not set by PHP');
      return false;
    }

    const storageKey = 'quizzy_timer_' + window.QUIZ_ATTEMPT_ID;
    const storedTime = localStorage.getItem(storageKey);
    const serverTime = parseInt(window.QUIZ_TIME_REMAINING, 10);

    // Server time is authoritative; use it if it's less than stored time
    if (storedTime !== null) {
      const storedSeconds = parseInt(storedTime, 10);
      timeRemaining = Math.min(serverTime, storedSeconds);
    } else {
      timeRemaining = serverTime;
    }

    // Clamp to 0 minimum
    timeRemaining = Math.max(0, timeRemaining);

    return true;
  }

  /**
   * Format seconds as MM:SS
   */
  function formatTime(seconds) {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return String(mins).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
  }

  /**
   * Update timer display and apply visual states
   */
  function updateTimerDisplay() {
    const timerElement = document.getElementById(TIMER_ELEMENT_ID);
    if (!timerElement) {
      console.warn('Timer element with id="' + TIMER_ELEMENT_ID + '" not found');
      return;
    }

    timerElement.textContent = formatTime(timeRemaining);

    // Apply warning styles when <= 60 seconds
    if (timeRemaining <= WARNING_THRESHOLD && timeRemaining > 0) {
      timerElement.classList.add(WARNING_CLASS, BOLD_CLASS);
      if (!timerElement.classList.contains(PULSE_CLASS)) {
        timerElement.classList.add(PULSE_CLASS);
        injectPulseAnimation();
      }
    } else if (timeRemaining > WARNING_THRESHOLD) {
      timerElement.classList.remove(WARNING_CLASS, BOLD_CLASS, PULSE_CLASS);
    }
  }

  /**
   * Inject CSS for pulse animation if not already present
   */
  function injectPulseAnimation() {
    // Check if style already exists
    if (document.getElementById('quizzy-pulse-animation')) {
      return;
    }

    const style = document.createElement('style');
    style.id = 'quizzy-pulse-animation';
    style.textContent = `
      @keyframes quizzy-pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.6; }
      }
      .pulse-animation {
        animation: quizzy-pulse 1s infinite;
      }
    `;
    document.head.appendChild(style);
  }

  /**
   * Persist current time to localStorage
   */
  function persistTime() {
    const storageKey = 'quizzy_timer_' + window.QUIZ_ATTEMPT_ID;
    localStorage.setItem(storageKey, String(timeRemaining));
  }

  /**
   * Auto-submit the quiz form
   */
  function autoSubmitQuiz() {
    if (hasSubmitted) {
      return;
    }
    hasSubmitted = true;

    const form = document.getElementById(window.QUIZ_SUBMIT_FORM_ID);
    if (!form) {
      console.error('Submit form with id="' + window.QUIZ_SUBMIT_FORM_ID + '" not found');
      return;
    }

    // Add a hidden field to indicate auto-submission
    const autoSubmitField = document.createElement('input');
    autoSubmitField.type = 'hidden';
    autoSubmitField.name = 'auto_submitted';
    autoSubmitField.value = '1';
    form.appendChild(autoSubmitField);

    form.submit();
  }

  /**
   * Tick the timer down by 1 second
   */
  function tick() {
    if (timeRemaining > 0) {
      timeRemaining--;
      persistTime();
      updateTimerDisplay();
    }

    if (timeRemaining <= 0) {
      // Stop interval and auto-submit
      clearInterval(timerInterval);
      autoSubmitQuiz();
    }
  }

  /**
   * Start the countdown timer
   */
  function startTimer() {
    // Initial display
    updateTimerDisplay();

    // Tick every second
    timerInterval = setInterval(tick, 1000);
  }

  /**
   * Clean up on page unload
   */
  function cleanup() {
    if (timerInterval) {
      clearInterval(timerInterval);
    }
    persistTime();
  }

  /**
   * Initialize on DOM ready
   */
  function init() {
    if (!initializeTimer()) {
      return;
    }

    startTimer();

    // Persist time before page unload
    window.addEventListener('beforeunload', cleanup);
    window.addEventListener('unload', cleanup);
  }

  // Start on DOMContentLoaded
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
