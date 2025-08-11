/**
 * Quiz Funnel Retention Tracker - Clean Production Version
 * Optimized tracking script for Nebroo quiz
 */

(function() {
    'use strict';

    // Configuration
    const CONFIG = {
        apiEndpoint: window.location.origin + '/server/api/track.php',
        retryAttempts: 3,
        retryDelay: 1000,
        sessionTimeout: 30 * 60 * 1000,
        debug: false
    };

    // Quiz slide mapping based on actual Nebroo quiz structure
    const SLIDE_CONFIG = {
        'slide-1': { title: "This year, I'm...", type: "question", sequence: 1 },
        'slide-2': { title: "What do you want the most?", type: "question", sequence: 2 },
        'slide-3': { title: "When did you notice you had a hearing problem?", type: "question", sequence: 3 },
        'slide-4': { title: "How has your life changed because of hearing loss?", type: "question", sequence: 4 },
        'slide-5': { title: "What happens when people speak?", type: "question", sequence: 5 },
        'slide-6': { title: "How would you describe your hearing loss level?", type: "question", sequence: 6 },
        'popup-1': { title: "100,000 people have chosen Nebroo to hear clearly again", type: "popup", sequence: 7 },
        'slide-7': { title: "What's the #1 reason why you haven't fixed your hearing?", type: "question", sequence: 8 },
        'slide-8': { title: "How do your family feel about your problem?", type: "question", sequence: 9 },
        'popup-2': { title: "Hearing loss doesn't have to ruin your life", type: "popup", sequence: 10 },
        'slide-9': { title: "Have you tried a prescription hearing aid?", type: "question", sequence: 11 },
        'slide-10': { title: "What are you most worried about?", type: "question", sequence: 12 },
        'slide-11': { title: "What is most important in a hearing aid?", type: "question", sequence: 13 }
    };

    class QuizTracker {
        constructor() {
            this.sessionId = this.generateSessionId();
            this.userId = this.getUserId();
            this.quizId = this.extractQuizId();
            this.currentSlide = null;
            this.slideStartTime = null;
            this.visitedSlides = new Set();
            this.answers = {};
            this.isCompleted = false;
            this.observer = null;
            this.retryQueue = [];
            this.pageExitTracked = false;
            this.pageStartTime = Date.now();
            
            this.init();
        }

        init() {
            try {
                this.trackPageEntry();
                this.setupSlideObserver();
                this.setupAnswerTracking();
                this.setupCompletionTracking();
                this.setupPageExitTracking();
                this.processRetryQueue();
                this.processStoredExitEvents();
            } catch (error) {
                console.error('Quiz Tracker initialization error:', error);
            }
        }

        generateSessionId() {
            const timestamp = Date.now();
            const random = Math.random().toString(36).substring(2, 15);
            return `session_${timestamp}_${random}`;
        }

        getUserId() {
            let userId = localStorage.getItem('nebroo_user_id');
            if (!userId) {
                userId = `user_${Date.now()}_${Math.random().toString(36).substring(2, 9)}`;
                localStorage.setItem('nebroo_user_id', userId);
            }
            return userId;
        }

        extractQuizId() {
            if (window.OVERRIDE_QUIZ_ID) {
                return window.OVERRIDE_QUIZ_ID;
            }
            const path = window.location.pathname;
            const match = path.match(/\/([^\/]+)$/);
            return match ? match[1] : 'unknown';
        }

        trackPageEntry() {
            const eventData = {
                page_url: window.location.href,
                referrer: document.referrer,
                user_agent: navigator.userAgent,
                screen_resolution: `${screen.width}x${screen.height}`,
                timezone: Intl.DateTimeFormat().resolvedOptions().timeZone
            };
            this.sendEvent('page_entry', eventData);
        }

        setupSlideObserver() {
            this.observer = new MutationObserver(() => {
                this.detectActiveSlide();
            });

            this.observer.observe(document.body, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ['class', 'style', 'data-slide']
            });

            setTimeout(() => this.detectActiveSlide(), 100);
        }

        detectActiveSlide() {
            const activeSelectors = [
                '.quiz-slide.active',
                '.slide.active', 
                '[data-slide].active',
                '.active[data-slide]',
                '.question.active',
                '.step.active'
            ];

            let activeSlide = null;
            for (const selector of activeSelectors) {
                activeSlide = document.querySelector(selector);
                if (activeSlide) break;
            }

            if (activeSlide) {
                const slideId = this.extractSlideId(activeSlide);
                if (slideId && slideId !== this.currentSlide) {
                    this.currentSlide = slideId;
                    this.trackSlideVisit(activeSlide);
                }
            }
        }

        extractSlideId(element) {
            const slideId = element.id || 
                           element.getAttribute('data-slide') ||
                           element.getAttribute('data-id') ||
                           element.getAttribute('data-step');

            if (slideId) {
                if (slideId.match(/^slide-\d+$/)) return slideId;
                if (slideId.match(/^popup-\d+$/)) return slideId;
                
                const numMatch = slideId.match(/^(\d+)$/);
                if (numMatch) {
                    const num = parseInt(numMatch[1]);
                    if (num === 7) return 'popup-1';
                    if (num === 10) return 'popup-2';
                    return `slide-${num}`;
                }
            }
            return null;
        }

        trackSlideVisit(slideElement) {
            const slideId = this.currentSlide;
            if (!slideId || this.visitedSlides.has(slideId)) return;

            this.visitedSlides.add(slideId);
            this.slideStartTime = Date.now();

            const slideConfig = SLIDE_CONFIG[slideId];
            const eventData = {
                slide_id: slideId,
                slide_title: slideConfig ? slideConfig.title : 'Unknown',
                slide_type: slideConfig ? slideConfig.type : 'unknown',
                sequence: slideConfig ? slideConfig.sequence : 0,
                time_on_page: Date.now() - this.pageStartTime
            };

            this.sendEvent('slide_visit', eventData);
        }

        setupAnswerTracking() {
            document.addEventListener('click', (event) => {
                const target = event.target.closest('[data-value], .answer-option, .option, .choice, .btn');
                if (target && this.currentSlide) {
                    this.trackAnswerSelection(target);
                }
            });
        }

        trackAnswerSelection(element) {
            const slideId = this.currentSlide;
            const slideConfig = SLIDE_CONFIG[slideId];
            
            if (!slideConfig || slideConfig.type !== 'question') return;

            let answerValue = element.getAttribute('data-value');
            let answerText = element.textContent?.trim() || '';

            if (!answerValue) {
                answerValue = answerText.toLowerCase().replace(/[^a-z0-9]/g, '_');
            }

            const eventData = {
                slide_id: slideId,
                slide_title: slideConfig.title,
                answer_value: answerValue,
                answer_text: answerText,
                time_on_slide: this.slideStartTime ? Date.now() - this.slideStartTime : 0
            };

            this.answers[slideId] = eventData;
            this.sendEvent('answer_selection', eventData);

            setTimeout(() => this.detectActiveSlide(), 500);
        }

        setupCompletionTracking() {
            const completionSelectors = [
                '.claim-discount-btn',
                '.summary-slide',
                '[data-action="complete"]',
                '.quiz-complete',
                '.completion-message'
            ];

            const checkForCompletion = () => {
                for (const selector of completionSelectors) {
                    if (document.querySelector(selector)) {
                        this.trackQuizCompletion();
                        return;
                    }
                }
            };

            setInterval(checkForCompletion, 2000);
        }

        trackQuizCompletion() {
            if (this.isCompleted) return;
            
            this.isCompleted = true;
            
            const eventData = {
                total_slides_visited: this.visitedSlides.size,
                answers_provided: Object.keys(this.answers).length,
                total_time: Date.now() - this.pageStartTime,
                completion_path: Array.from(this.visitedSlides)
            };

            this.sendEvent('quiz_completion', eventData);
        }

        setupPageExitTracking() {
            const events = ['beforeunload', 'pagehide', 'unload', 'visibilitychange'];
            
            events.forEach(eventType => {
                window.addEventListener(eventType, () => {
                    if (this.isCompleted || this.pageExitTracked) return;
                    this.trackPageExit();
                }, true);
            });

            window.addEventListener('beforeunload', (event) => {
                if (this.isCompleted || this.pageExitTracked) return;
                
                this.pageExitTracked = true;
                
                let lastSlide = this.currentSlide;
                if (!lastSlide) {
                    const activeSlide = document.querySelector('.quiz-slide.active, .slide.active, [data-slide].active');
                    if (activeSlide) {
                        lastSlide = this.extractSlideId(activeSlide);
                    }
                }
                if (!lastSlide) {
                    const visitedSlides = Array.from(this.visitedSlides);
                    lastSlide = visitedSlides.length > 0 ? visitedSlides[visitedSlides.length - 1] : 'slide-1';
                }

                const eventData = {
                    total_time: Date.now() - this.pageStartTime,
                    last_slide: lastSlide,
                    slides_visited: this.visitedSlides.size,
                    answers_provided: Object.keys(this.answers).length,
                    exit_reason: 'tab_close'
                };

                const payload = {
                    quiz_id: this.quizId,
                    session_id: this.sessionId,
                    user_id: this.userId,
                    event: {
                        type: 'page_exit',
                        timestamp: Date.now(),
                        data: eventData
                    }
                };

                if (navigator.sendBeacon) {
                    navigator.sendBeacon(CONFIG.apiEndpoint, JSON.stringify(payload));
                } else {
                    try {
                        const xhr = new XMLHttpRequest();
                        xhr.open('POST', CONFIG.apiEndpoint, false);
                        xhr.setRequestHeader('Content-Type', 'application/json');
                        xhr.send(JSON.stringify(payload));
                    } catch (error) {
                        this.storeExitEvent(payload);
                    }
                }
            });
        }

        trackPageExit() {
            if (this.isCompleted || this.pageExitTracked) return;
            
            this.pageExitTracked = true;

            let lastSlide = this.currentSlide;
            if (!lastSlide) {
                const activeSlide = document.querySelector('.quiz-slide.active, .slide.active, [data-slide].active');
                if (activeSlide) {
                    lastSlide = this.extractSlideId(activeSlide);
                }
            }
            if (!lastSlide) {
                const visitedSlides = Array.from(this.visitedSlides);
                lastSlide = visitedSlides.length > 0 ? visitedSlides[visitedSlides.length - 1] : 'slide-1';
            }

            const eventData = {
                total_time: Date.now() - this.pageStartTime,
                last_slide: lastSlide,
                slides_visited: this.visitedSlides.size,
                answers_provided: Object.keys(this.answers).length,
                exit_reason: document.visibilityState === 'hidden' ? 'visibility_change' : 'page_unload'
            };

            this.sendEvent('page_exit', eventData);
        }

        async sendEvent(eventType, eventData) {
            const payload = {
                quiz_id: this.quizId,
                session_id: this.sessionId,
                user_id: this.userId,
                event: {
                    type: eventType,
                    timestamp: Date.now(),
                    data: eventData
                }
            };

            try {
                const response = await fetch(CONFIG.apiEndpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(payload)
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
            } catch (error) {
                this.addToRetryQueue(payload);
            }
        }

        addToRetryQueue(payload) {
            this.retryQueue.push(payload);
            if (this.retryQueue.length > 10) {
                this.retryQueue.shift();
            }
        }

        async processRetryQueue() {
            if (this.retryQueue.length === 0) return;

            const payload = this.retryQueue.shift();
            try {
                await this.sendEvent(payload.event.type, payload.event.data);
            } catch (error) {
                this.retryQueue.push(payload);
            }

            setTimeout(() => this.processRetryQueue(), 5000);
        }

        storeExitEvent(payload) {
            try {
                const storedEvents = JSON.parse(localStorage.getItem('nebroo_exit_events') || '[]');
                storedEvents.push(payload);
                localStorage.setItem('nebroo_exit_events', JSON.stringify(storedEvents));
            } catch (error) {
                console.error('Error storing exit event:', error);
            }
        }

        async processStoredExitEvents() {
            try {
                const storedEvents = JSON.parse(localStorage.getItem('nebroo_exit_events') || '[]');
                if (storedEvents.length === 0) return;

                for (const payload of storedEvents) {
                    try {
                        await this.sendEvent(payload.event.type, payload.event.data);
                    } catch (error) {
                        continue;
                    }
                }

                localStorage.removeItem('nebroo_exit_events');
            } catch (error) {
                console.error('Error processing stored exit events:', error);
            }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            window.quizTracker = new QuizTracker();
        });
    } else {
        window.quizTracker = new QuizTracker();
    }

})(); 