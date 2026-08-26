<p>Você foi convidado para participar do projeto <strong>{{ $projectName }}</strong> no Ganttist.</p>
<p>Seu acesso será de <strong>{{ $role === 'editor' ? 'alteração' : 'leitura' }}</strong>.</p>
<p>Entre no Ganttist com este mesmo e-mail para aceitar o convite. Ele ficará disponível até {{ $expiresAt->locale('pt_BR')->translatedFormat('d \\d\\e F \\d\\e Y') }}.</p>
