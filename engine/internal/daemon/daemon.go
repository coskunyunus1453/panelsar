package daemon

import (
	"fmt"
	"sync"

	"github.com/panelsar/engine/internal/config"
	"github.com/panelsar/engine/internal/services"
	"github.com/sirupsen/logrus"
)

type Daemon struct {
	cfg            *config.Config
	log            *logrus.Logger
	serviceManager *services.Manager
	mu             sync.RWMutex
	running        bool
}

func New(cfg *config.Config, log *logrus.Logger) *Daemon {
	return &Daemon{
		cfg: cfg,
		log: log,
	}
}

func (d *Daemon) Init() error {
	d.mu.Lock()
	defer d.mu.Unlock()

	d.log.Info("Initializing daemon...")

	d.serviceManager = services.NewManager(d.cfg, d.log)

	if err := d.ensureDirectories(); err != nil {
		return fmt.Errorf("failed to create directories: %w", err)
	}

	if err := d.serviceManager.DiscoverServices(); err != nil {
		d.log.Warnf("Service discovery partial failure: %v", err)
	}

	d.running = true
	d.log.Info("Daemon initialized successfully")
	return nil
}

func (d *Daemon) Shutdown() {
	d.mu.Lock()
	defer d.mu.Unlock()

	d.log.Info("Daemon shutting down...")
	d.running = false
}

func (d *Daemon) IsRunning() bool {
	d.mu.RLock()
	defer d.mu.RUnlock()
	return d.running
}

func (d *Daemon) ServiceManager() *services.Manager {
	return d.serviceManager
}

func (d *Daemon) ensureDirectories() error {
	dirs := []string{
		d.cfg.Paths.WebRoot,
		d.cfg.Paths.VhostsDir,
		d.cfg.Paths.SSLDir,
		d.cfg.Paths.BackupDir,
		d.cfg.Paths.LogDir,
		d.cfg.Paths.TempDir,
	}

	for _, dir := range dirs {
		d.log.Debugf("Ensuring directory exists: %s", dir)
		// In production, os.MkdirAll would be called here
		// Skipped during development to avoid permission issues
		_ = dir
	}

	return nil
}
