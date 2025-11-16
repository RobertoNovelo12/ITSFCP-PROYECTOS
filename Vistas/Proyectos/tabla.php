<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
if (isset($_SESSION['rol'])) {
    $base_url = "/ITSFCP-PROYECTOS/";

    switch (strtolower($_SESSION['rol'])) {
        case 'alumno':
            header("Location: {$base_url}../../Vistas/usuarios/alumno.php");
            exit;
        case 'profesor':
        case 'investigador':
            header("Location: {$base_url}../../Vistas/usuarios/profesor.php");
            exit;
        case 'supervisor':
            header("Location: {$base_url}../../Vistas/usuarios/supervisor.php");
            exit;
    }
    $rol = $_SESSION['rol'];
    $id = $_SESSION['id_usuario'];
}
?>


<?php include '../../publico/incluido/header.php'; ?>
<div class="main-content">
    <?php include '../../sidebar_publico.php'; ?>
    <div class="col-12">
        <div class="m3-3">
            <h3>Proyectos</h3>
        </div>
    <div class="row mb-3">
        <div class="col-12 d-flex justify-content-between align-items-center">
                <div class="col-7">
                    <div class="btn-group" role="group" aria-label="Filtros">
                        <button type="button" class="btn btn-outline-primary">Todos (2)</button>
                        <button type="button" class="btn btn-outline-primary">Activos (1)</button>
                        <button type="button" class="btn btn-outline-primary">Completados (1)</button>
                        <button type="button" class="btn btn-outline-primary">Archivados (0)</button>
                    </div>
                </div>
                <div class="col-5">
                    <form class="d-flex align-items-center" role="search">
                        <div class="me-3">
                            <label class="form-label mb-0 small">Período</label>
                            <select class="form-select">
                                <option>Todos</option>
                            </select>
                        </div>
                        <div class="flex-grow-1">
                            <label class="form-label mb-0 small">Buscar</label>
                            <div class="input-group">
                                <input class="form-control" type="search" placeholder="Buscar" aria-label="Search" />
                                <button class="btn btn-outline-success" type="submit">Buscar</button>
                            </div>
                        </div>
                    </form>
                </div>
        </div>
    </div>
    <div class="row">
        <div class="col-12">
            <div class="table-responsive">
            <table class="table table-light">
                <thead>
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">First</th>
                        <th scope="col">Last</th>
                        <th scope="col">Handle</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <th scope="row">1</th>
                        <td>Mark</td>
                        <td>Otto</td>
                        <td>@mdo</td>
                    </tr>
                    <tr>
                        <th scope="row">2</th>
                        <td>Jacob</td>
                        <td>Thornton</td>
                        <td>@fat</td>
                    </tr>
                    <tr>
                        <th scope="row">3</th>
                        <td>John</td>
                        <td>Doe</td>
                        <td>@social</td>
                    </tr>
                </tbody>
            </table>
        </div>
        </div>
    </div>
    </div>
</div>
<?php include "../../publico/incluido/footer.php"; ?>
