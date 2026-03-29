#!/bin/bash
set -e

echo "========================================"
echo "  Panelsar - Development Setup"
echo "========================================"
echo ""

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

# Check prerequisites
echo -e "${YELLOW}Checking prerequisites...${NC}"

check_command() {
    if command -v "$1" &> /dev/null; then
        echo -e "  ${GREEN}✓${NC} $1 found"
        return 0
    else
        echo -e "  ${RED}✗${NC} $1 not found"
        return 1
    fi
}

check_command "go" || { echo "Install Go: https://go.dev/dl/"; exit 1; }
check_command "php" || { echo "Install PHP 8.2+"; exit 1; }
check_command "composer" || { echo "Install Composer: https://getcomposer.org"; exit 1; }
check_command "node" || { echo "Install Node.js 18+: https://nodejs.org"; exit 1; }
check_command "npm" || { echo "Install npm"; exit 1; }
check_command "docker" || { echo "Install Docker: https://docker.com"; exit 1; }

echo ""
echo -e "${YELLOW}Setting up Docker services...${NC}"
cd docker && docker compose up -d && cd ..
echo -e "${GREEN}Docker services started${NC}"

echo ""
echo -e "${YELLOW}Setting up Go Engine...${NC}"
cd engine
go mod tidy 2>/dev/null || true
echo -e "${GREEN}Engine dependencies resolved${NC}"
cd ..

echo ""
echo -e "${YELLOW}Setting up Laravel Panel...${NC}"
cd panel
cp .env.example .env 2>/dev/null || true
composer install --no-interaction 2>/dev/null || echo "Run 'composer install' manually in panel/"
php artisan key:generate 2>/dev/null || true
echo -e "${GREEN}Panel setup complete${NC}"
cd ..

echo ""
echo -e "${YELLOW}Setting up React Frontend...${NC}"
cd frontend
npm install
echo -e "${GREEN}Frontend dependencies installed${NC}"
cd ..

echo ""
echo "========================================"
echo -e "${GREEN}  Setup Complete!${NC}"
echo "========================================"
echo ""
echo "Start development:"
echo "  make dev          - Start all services"
echo "  make engine-dev   - Start Go engine only"
echo "  make panel        - Start Laravel panel only"
echo "  make frontend     - Start React frontend only"
echo ""
echo "Services:"
echo "  Frontend:    http://localhost:3000"
echo "  Panel API:   http://localhost:8000"
echo "  Engine API:  http://localhost:9090"
echo "  phpMyAdmin:  http://localhost:8081"
echo "  Mailhog:     http://localhost:8025"
echo ""
echo "Default credentials:"
echo "  Admin:    admin@panelsar.com / password"
echo "  Reseller: reseller@panelsar.com / password"
echo "  User:     user@panelsar.com / password"
