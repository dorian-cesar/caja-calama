// caja-control.js
$(document).ready(function () {
    const modalHtml = `
    <div class="modal fade" id="modalCajaGlobal" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content bg-dark text-white">
          <div class="modal-header">
            <h5 class="modal-title">Abrir Caja</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <form id="formCajaGlobal">
              <label>Monto Inicial</label>
              <input type="number" step="0.01" class="form-control mb-3" id="montoInicialGlobal" required>
              <button class="btn btn-success" type="submit">Abrir Caja</button>
            </form>
          </div>
        </div>
      </div>
    </div>`;

    const mensajeHtml = `<div id="mensajeCajaGlobal" class="container mt-3"></div>`;

    $('body').append(modalHtml);
    $('body').prepend(mensajeHtml);

    const idCaja = localStorage.getItem("id_caja");

    if (idCaja) {
        $('#mensajeCajaGlobal').html(`
            <div class="alert alert-info">Caja abierta con ID: ${idCaja}</div>
        `);
    } else {
        const modal = new bootstrap.Modal(document.getElementById('modalCajaGlobal'));
        modal.show();

        $('#formCajaGlobal').on('submit', function (e) {
            e.preventDefault();
            const monto = $('#montoInicialGlobal').val();
            $.post('caja.php', { accion: 'abrir', monto_inicial: monto }, function (res) {
                const data = JSON.parse(res);
                if (data.success) {
                    localStorage.setItem('id_caja', data.id);
                    modal.hide();
                    $('#mensajeCajaGlobal').html(`
                        <div class="alert alert-success">Caja abierta con ID: ${data.id}</div>
                    `);
                } else {
                    $('#mensajeCajaGlobal').html(`
                        <div class="alert alert-danger">${data.error}</div>
                    `);
                }
            });
        });
    }
});
