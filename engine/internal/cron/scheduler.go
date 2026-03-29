package cron

import (
	"fmt"
	"sync"
	"time"

	"github.com/sirupsen/logrus"
)

type JobStatus string

const (
	JobActive   JobStatus = "active"
	JobPaused   JobStatus = "paused"
	JobDisabled JobStatus = "disabled"
)

type CronJob struct {
	ID         string    `json:"id"`
	UserID     uint      `json:"user_id"`
	Schedule   string    `json:"schedule"`
	Command    string    `json:"command"`
	Status     JobStatus `json:"status"`
	LastRun    time.Time `json:"last_run"`
	NextRun    time.Time `json:"next_run"`
	CreatedAt  time.Time `json:"created_at"`
}

type Scheduler struct {
	log  *logrus.Logger
	jobs map[string]*CronJob
	mu   sync.RWMutex
}

func NewScheduler(log *logrus.Logger) *Scheduler {
	return &Scheduler{
		log:  log,
		jobs: make(map[string]*CronJob),
	}
}

func (s *Scheduler) AddJob(userID uint, schedule, command string) (*CronJob, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	id := fmt.Sprintf("cron_%d_%d", userID, time.Now().UnixNano())

	job := &CronJob{
		ID:        id,
		UserID:    userID,
		Schedule:  schedule,
		Command:   command,
		Status:    JobActive,
		CreatedAt: time.Now(),
	}

	s.jobs[id] = job
	s.log.Infof("Added cron job %s for user %d: %s", id, userID, schedule)
	return job, nil
}

func (s *Scheduler) RemoveJob(id string) error {
	s.mu.Lock()
	defer s.mu.Unlock()

	if _, ok := s.jobs[id]; !ok {
		return fmt.Errorf("cron job %s not found", id)
	}

	delete(s.jobs, id)
	s.log.Infof("Removed cron job %s", id)
	return nil
}

func (s *Scheduler) PauseJob(id string) error {
	s.mu.Lock()
	defer s.mu.Unlock()

	job, ok := s.jobs[id]
	if !ok {
		return fmt.Errorf("cron job %s not found", id)
	}

	job.Status = JobPaused
	return nil
}

func (s *Scheduler) ResumeJob(id string) error {
	s.mu.Lock()
	defer s.mu.Unlock()

	job, ok := s.jobs[id]
	if !ok {
		return fmt.Errorf("cron job %s not found", id)
	}

	job.Status = JobActive
	return nil
}

func (s *Scheduler) GetUserJobs(userID uint) []CronJob {
	s.mu.RLock()
	defer s.mu.RUnlock()

	var result []CronJob
	for _, job := range s.jobs {
		if job.UserID == userID {
			result = append(result, *job)
		}
	}
	return result
}

func (s *Scheduler) GetJob(id string) (*CronJob, error) {
	s.mu.RLock()
	defer s.mu.RUnlock()

	job, ok := s.jobs[id]
	if !ok {
		return nil, fmt.Errorf("cron job %s not found", id)
	}
	return job, nil
}
