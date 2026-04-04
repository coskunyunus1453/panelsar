package main

import (
	"context"
	"fmt"
	"net/http"
	"os"
	"os/signal"
	"syscall"
	"time"

	"hostvim/engine/internal/api"
	"hostvim/engine/internal/config"
	"hostvim/engine/internal/daemon"
	"hostvim/engine/pkg/logger"
)

const (
	appName    = "Hostvim Engine"
	appVersion = "0.1.0"
)

func main() {
	log := logger.New()
	log.Infof("Starting %s v%s", appName, appVersion)

	cfg, err := config.Load()
	if err != nil {
		log.Fatalf("Failed to load config: %v", err)
	}

	d := daemon.New(cfg, log)
	if err := d.Init(); err != nil {
		log.Fatalf("Failed to initialize daemon: %v", err)
	}

	router := api.NewRouter(cfg, d, log)

	srv := &http.Server{
		Addr:         fmt.Sprintf(":%d", cfg.Server.Port),
		Handler:      router,
		ReadTimeout:  15 * time.Second,
		WriteTimeout: 15 * time.Second,
		IdleTimeout:  60 * time.Second,
	}

	go func() {
		log.Infof("Engine API listening on port %d", cfg.Server.Port)
		if err := srv.ListenAndServe(); err != nil && err != http.ErrServerClosed {
			log.Fatalf("Server error: %v", err)
		}
	}()

	quit := make(chan os.Signal, 1)
	signal.Notify(quit, syscall.SIGINT, syscall.SIGTERM)
	<-quit

	log.Info("Shutting down engine...")
	ctx, cancel := context.WithTimeout(context.Background(), 30*time.Second)
	defer cancel()

	if err := srv.Shutdown(ctx); err != nil {
		log.Fatalf("Forced shutdown: %v", err)
	}

	d.Shutdown()
	log.Info("Engine stopped gracefully")
}
